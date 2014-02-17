<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class PUNBB_Converter_Module_Posts extends Converter_Module_Posts {

	var $settings = array(
		'friendly_name' => 'posts',
		'progress_column' => 'id',
		'default_per_screen' => 1000,
		'check_table_type' => 'posts',
	);

	function import()
	{
		global $import_session, $db;

		$query = $this->old_db->simple_select("posts", "*", "", array('limit_start' => $this->trackers['start_posts'], 'limit' => $import_session['posts_per_screen']));
		while($post = $this->old_db->fetch_array($query))
		{
			$pid = $this->insert($post);

			if($insert_post['replyto'] == 0)
			{
				$db->update_query("threads", array('firstpost' => $pid), "import_tid='{$post['topic_id']}'");
			}
		}
	}

	function convert_data($data)
	{
		global $db;

		$insert_data = array();

		// punBB values
		$insert_data['import_pid'] = $data['id'];
		$insert_data['tid'] = $this->get_import->tid($data['topic_id']);

		// Find if this is the first post in thread
		$query = $db->simple_select("threads", "*", "tid='{$insert_data['tid']}'");
		$thread = $db->fetch_array($query);
		$first_post = $thread['import_firstpost'];
		$db->free_result($query);

		// Make the replyto the first post of thread unless it is the first post
		if($first_data == $data['post_id'])
		{
			$insert_data['replyto'] = 0;
		}
		else
		{
			$insert_data['replyto'] = $first_post;
		}

		$insert_data['subject'] = encode_to_utf8($thread['subject'], "topics", "posts");

		// Check usernames for guests
		$data['username'] = $this->get_import->username($data['poster_id'], $data['poster']);

		$insert_data['fid'] = $thread['fid'];
		$insert_data['uid'] = $this->get_import->uid($data['poster_id']);
		$insert_data['import_uid'] = $data['poster_id'];
		$insert_data['username'] = $data['poster'];
		$insert_data['dateline'] = $data['posted'];
		$insert_data['message'] = encode_to_utf8($this->bbcode_parser->convert($data['message']), "posts", "posts");
		$insert_data['ipaddress'] = my_inet_pton($data['poster_ip']);
		$insert_data['smilieoff'] = $data['hide_smilies'];
		if($data['edited'] != 0)
		{
			$user = $this->board->get_user($data['edited_by']);
			$insert_data['edituid'] = $user['id'];
			$insert_data['edittime'] = $data['edited'];
		}
		else
		{
			$insert_data['edituid'] = 0;
			$insert_data['edittime'] = 0;
		}

		return $insert_data;
	}

	/**
	 * Get a user from the SMF database
	 *
	 * @param int User ID
	 * @return array If the uid is 0, returns an array of posterName and memberName as Guest.  Otherwise returns the user
	 */
	function get_user($uid)
	{
		$uid = intval($uid);
		if(empty($uid))
		{
			return array(
				'posterName' => 'Guest',
				'memberName' => 'Guest'
			);
		}

		$query = $this->old_db->simple_select("members", "*", "ID_MEMBER = '{$uid}'", array('limit' => 1));

		$result = $this->old_db->fetch_array($query);
		$this->old_db->free_result($query);

		return $results;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of posts
		if(!isset($import_session['total_posts']))
		{
			$query = $this->old_db->simple_select("posts", "COUNT(*) as count");
			$import_session['total_posts'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_posts'];
	}
}

?>