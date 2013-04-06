<?php
/**
 * MyBB 1.6
 * Copyright 2009 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 * $Id$
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class BBPRESS_Converter_Module_Posts extends Converter_Module_Posts {

	var $settings = array(
		'friendly_name' => 'posts',
		'progress_column' => 'post_id',
		'default_per_screen' => 1000,
		'check_table_type' => 'posts',
	);

	function import()
	{
		global $import_session;
		
		$query = $this->old_db->simple_select("posts", "*", "post_status != '1'", array('limit_start' => $this->trackers['start_posts'], 'limit' => $import_session['posts_per_screen']));
		while($post = $this->old_db->fetch_array($query))
		{
			$this->insert($post);
		}
	}
	
	function convert_data($data)
	{
		$insert_data = array();
		
		// bbPress values	
		$insert_data['import_pid'] = $data['post_id'];
		$insert_data['tid'] = $this->get_import->tid($data['topic_id']);
		$insert_data['fid'] = $this->get_import->fid($data['forum_id']);
		//$insert_data['subject'] = encode_to_utf8($this->bbcode_parser->convert_title($data['post_subject']), "posts", "posts");
		$insert_data['uid'] = $this->get_import->uid($data['poster_id']);
		$insert_data['import_uid'] = $data['poster_id'];
		$insert_data['username'] = $this->get_import->username($data['poster_id']);
		$insert_data['dateline'] = strtotime($data['post_time']);
		$insert_data['message'] = encode_to_utf8($this->bbcode_parser->convert($data['post_text'], $data['bbcode_uid']), "posts", "posts");
		$insert_data['ipaddress'] = $data['poster_ip'];
		
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
			$query = $this->old_db->simple_select("posts", "COUNT(*) as count", "post_status != '1'");
			$import_session['total_posts'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}
		
		return $import_session['total_posts'];
	}
}

?>