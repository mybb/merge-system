<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 *
 * $Id: posts.php 4394 2010-12-14 14:38:21Z ralgith $
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class PHPBB2_Converter_Module_Posts extends Converter_Module_Posts {

	var $settings = array(
		'friendly_name' => 'posts',
		'progress_column' => 'post_id',
		'default_per_screen' => 1000,
		'check_table_type' => array("posts", "posts_text"),
	);

	function import()
	{
		global $import_session;

		$query = $this->old_db->query("
			SELECT p.*, pt.*
			FROM ".OLD_TABLE_PREFIX."posts p
			LEFT JOIN ".OLD_TABLE_PREFIX."posts_text pt ON(p.post_id=pt.post_id)
			LIMIT ".$this->trackers['start_posts'].", ".$import_session['posts_per_screen']
		);
		while($post = $this->old_db->fetch_array($query))
		{
			$this->insert($post);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// phpBB 2 values
		$insert_data['import_pid'] = $data['post_id'];
		$insert_data['tid'] = $this->get_import->tid($data['topic_id']);

		// Check the last post for any NULL's, converted by phpBB's parser to a default topic
		if($data['post_subject'] === 'NULL')
		{
			$data['post_subject'] = 'Welcome to phpBB 2';
		}

		// Get Username
		$topic_poster = $this->get_user($data['poster_id']);

		// Check to see if we need to inherit any post subjects from the thread
		if(empty($data['post_subject']))
		{
			$query = $this->old_db->simple_select("topics", "topic_first_post_id, topic_title", "topic_first_post_id='{$data['post_id']}'", array('limit' => 1));
			$topic = $this->old_db->fetch_array($query);
			$this->old_db->free_result($query);

			if($topic['topic_first_post_id'] == $data['post_id'])
			{
				$data['post_subject'] = 'RE: '.$topic['topic_title'];
			}
		}

		$insert_data['fid'] = $this->get_import->fid_f($data['forum_id']);
		$insert_data['subject'] = encode_to_utf8(utf8_unhtmlentities($data['post_subject']), "posts", "posts");
		$insert_data['uid'] = $this->get_import->uid($data['poster_id']);
		$insert_data['import_uid'] = $data['poster_id'];
		$insert_data['username'] = $this->get_import->username($data['poster_id'], $topic_poster['username']);
		$insert_data['dateline'] = $data['post_time'];
		$insert_data['message'] = encode_to_utf8($this->bbcode_parser->convert($data['post_text'], $data['bbcode_uid']), "posts", "posts");
		$insert_data['ipaddress'] = my_inet_pton($this->decode_ip($data['poster_ip']));
		$insert_data['includesig'] = $data['enable_sig'];
		$insert_data['smilieoff'] = int_to_01($data['enable_smilies']);

		return $insert_data;
	}

	function after_insert($data, $insert_data, $pid)
	{
		global $db;

		$db->update_query("threads", array('firstpost' => $pid), "tid = '{$insert_data['tid']}' AND import_firstpost = '{$insert_data['import_pid']}'");
		if($db->affected_rows() == 0)
		{
			$query = $db->simple_select("threads", "firstpost", "tid = '{$insert_data['tid']}'");
			$first_post = $db->fetch_field($query, "firstpost");
			$db->free_result($query);
			$db->update_query("posts", array('replyto' => $first_post), "pid = '{$pid}'");
		}
	}

	/**
	 * Get a user from the phpBB database
	 *
	 * @param int User ID
	 * @return array If the uid is 0, returns an array of username as Guest.  Otherwise returns the user
	 */
	function get_user($uid)
	{
		if($uid == 0)
		{
			return array(
				'username' => 'Guest',
				'user_id' => 0,
			);
		}

		$query = $this->old_db->simple_select("users", "*", "user_id='{$uid}'", array('limit' => 1));
		$results = $this->old_db->fetch_array($query);
		$this->old_db->free_result($query);

		return $results;
	}

	/**
	 * Decode function for phpBB's IP Addresses
	 *
	 * @param string Encoded IP Address
	 * @return string Decoded IP Address
	 */
	function decode_ip($ip)
	{
		$hex_ip = explode('.', chunk_split($ip, 2, '.'));
		return hexdec($hex_ip[0]). '.' . hexdec($hex_ip[1]) . '.' . hexdec($hex_ip[2]) . '.' . hexdec($hex_ip[3]);
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