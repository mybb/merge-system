<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2011 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class VBULLETIN3_Converter_Module_Posts extends Converter_Module_Posts {

	var $settings = array(
		'friendly_name' => 'posts',
		'progress_column' => 'postid',
		'default_per_screen' => 1000,
		'check_table_type' => 'post',
	);

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select("post", "*", "", array('limit_start' => $this->trackers['start_posts'], 'limit' => $import_session['posts_per_screen']));
		while($post = $this->old_db->fetch_array($query))
		{
			$this->insert($post);
		}
	}

	function convert_data($data)
	{
		global $db;

		// vBulletin 3 values
		$insert_data['import_pid'] = $data['postid'];
		$insert_data['tid'] = $this->get_import->tid($data['threadid']);
		$thread = $this->get_thread($data['threadid']);
		$insert_data['fid'] = $this->get_import->fid($thread['forumid']);
		$insert_data['subject'] = encode_to_utf8(str_replace('&quot;', '"', $thread['title']), "thread", "posts");
		if(strlen($insert_data['subject']) > 120)
		{
			$insert_data['subject'] = substr($insert_data['subject'], 0, 117)."...";
		}
		$insert_data['visible'] = $data['visible'];
		$insert_data['uid'] = $this->get_import->uid($data['userid']);
		$insert_data['import_uid'] = $data['userid'];
		$insert_data['username'] = $this->get_import->username($insert_data['import_uid'], $data['username']);
		$insert_data['dateline'] = $data['dateline'];
		$insert_data['message'] = encode_to_utf8($this->bbcode_parser->convert($data['pagetext']), "post", "posts");
		$insert_data['ipaddress'] = $data['ipaddress'];
		$edit = $this->get_editlog($data['postid']);
		$insert_data['edituid'] = $this->get_import->uid($edit['userid']);
		$insert_data['edittime'] = $edit['dateline'];
		$insert_data['includesig'] = $data['showsignature'];
		$insert_data['smilieoff'] = int_to_01($data['allowsmilie']);

		return $insert_data;
	}

	function after_insert($data, $insert_data, $pid)
	{
		global $db;

		// Restore first post connections
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
	 * Get a thread from the vB database
	 *
	 * @param int Thread ID
	 * @return array The thread
	 */
	function get_thread($tid)
	{
		$tid = intval($tid);
		$query = $this->old_db->simple_select("thread", "forumid,title", "threadid = '{$tid}'", array('limit' => 1));
		$results = $this->old_db->fetch_array($query);
		$this->old_db->free_result($query);

		return $results;
	}

	/**
	 * Get a edit log from the vB database
	 *
	 * @param int Post ID
	 * @return array The edit log
	 */
	function get_editlog($pid)
	{
		$pid = intval($pid);
		$query = $this->old_db->simple_select("editlog", "userid,dateline", "postid = '{$pid}'", array('limit' => 1));
		$results = $this->old_db->fetch_array($query);
		$this->old_db->free_result($query);

		return $results;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of posts
		if(!isset($import_session['total_posts']))
		{
			$query = $this->old_db->simple_select("post", "COUNT(*) as count");
			$import_session['total_posts'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_posts'];
	}
}

?>