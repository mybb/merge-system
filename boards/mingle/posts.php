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

class MINGLE_Converter_Module_Posts extends Converter_Module_Posts {

	var $settings = array(
		'friendly_name' => 'posts',
		'progress_column' => 'id',
		'default_per_screen' => 1000,
		'check_table_type' => 'posts',
	);

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select("forum_posts", "*", "1=1", array('limit_start' => $this->trackers['start_posts'], 'limit' => $import_session['posts_per_screen']));
		while($post = $this->old_db->fetch_array($query))
		{
			$this->insert($post);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// Mingle values
		$insert_data['import_pid'] = $data['id'];
		$insert_data['tid'] = $this->get_import->tid($data['parent_id']);
		$insert_data['fid'] = $this->get_import->fid($this->get_fid_from_tid($data['parent_id']));
		$insert_data['subject'] = encode_to_utf8($this->bbcode_parser->convert_title($data['subject']), "forum_posts", "posts");
		$insert_data['uid'] = $this->get_import->uid($data['author_id']);
		$insert_data['import_uid'] = $data['author_id'];
		$insert_data['username'] = $this->get_import->username($data['author_id']);
		$insert_data['dateline'] = strtotime($data['date']);
		$insert_data['message'] = encode_to_utf8($this->bbcode_parser->convert($data['text']), "forum_posts", "posts");
		//$insert_data['ipaddress'] = "";

		return $insert_data;
	}

	function after_insert($data, $insert_data, $pid)
	{
		global $db;

		// Restore first post connections
		$db->write_query("UPDATE `".TABLE_PREFIX."threads` t SET firstpost=(SELECT MIN(pid) FROM `".TABLE_PREFIX."posts` p WHERE t.tid=p.tid)");
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of posts
		if(!isset($import_session['total_posts']))
		{
			$query = $this->old_db->simple_select("forum_posts", "COUNT(*) as count", "1=1");
			$import_session['total_posts'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_posts'];
	}

	/**
	 * Get old forum id from old thread id since Mingle only stores tid with post
	 *
	 * @param int tid
	 * @return fid
	 */
	function get_fid_from_tid($tid)
	{
		$query = $this->old_db->simple_select("forum_threads", "parent_id", "id={$tid}");
		return $this->old_db->fetch_field($query, 'parent_id');
	}
}

?>
