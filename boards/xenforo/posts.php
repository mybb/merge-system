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

class XENFORO_Converter_Module_Posts extends Converter_Module_Posts {

	var $settings = array(
		'friendly_name' => 'posts',
		'progress_column' => 'post_id',
		'default_per_screen' => 1000,
		'check_table_type' => 'post',
	);

	function import()
	{
		global $import_session;
		
		$query = $this->old_db->query("SELECT p.*, t.node_id, t.title, i.ip
				FROM ".OLD_TABLE_PREFIX."post p
				LEFT JOIN ".OLD_TABLE_PREFIX."thread t ON(t.thread_id=p.thread_id)
				LEFT JOIN ".OLD_TABLE_PREFIX."ip i ON(i.ip_id=p.ip_id)
				LIMIT {$this->trackers['start_posts']}, {$import_session['posts_per_screen']}");
		while($post = $this->old_db->fetch_array($query))
		{
			$this->insert($post);
		}
	}
	
	function convert_data($data)
	{
		// Xenforo 1 values
		$insert_data['import_pid'] = $data['post_id'];
		$insert_data['tid'] = $this->get_import->tid($data['thread_id']);
		$insert_data['fid'] = $this->get_import->fid($data['node_id']);
		$insert_data['subject'] = encode_to_utf8($data['title'], "thread", "posts");
		$insert_data['uid'] = $this->get_import->uid($data['user_id']);
		$insert_data['import_uid'] = $data['user_id'];
		$insert_data['username'] = $this->get_import->username($insert_data['import_uid'], $data['username']);
		$insert_data['dateline'] = $data['post_date'];
		$insert_data['message'] = encode_to_utf8($this->bbcode_parser->convert($data['message']), "post", "posts");
		$insert_data['ipaddress'] = $data['ip'];

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


