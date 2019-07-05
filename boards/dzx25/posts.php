<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2019 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class DZX25_Converter_Module_Posts extends Converter_Module_Posts {
	
	var $settings = array(
			'friendly_name' => 'posts',
			'progress_column' => 'pid',
			'default_per_screen' => 1000,
			'check_table_type' => 'forum_post',
	);
	
	function import()
	{
		global $import_session;
		
		$query = $this->old_db->simple_select("forum_post", "*", "", array('order_by' => 'pid', 'order_dir' => 'ASC', 'limit_start' => $this->trackers['start_posts'], 'limit' => $import_session['posts_per_screen']));
		while($post = $this->old_db->fetch_array($query))
		{
			$this->insert($post);
		}
	}
	
	function convert_data($data)
	{
		$insert_data = array();
		
		// Discuz! values.
		$insert_data['import_pid'] = $data['pid'];
		$insert_data['import_uid'] = $data['authorid'];
		
		$insert_data['tid'] = $this->get_import->tid($data['tid']);
		$insert_data['fid'] = $this->get_import->fid($data['fid']);
		$insert_data['subject'] = encode_to_utf8(utf8_unhtmlentities($data['subject']), "forum_post", "posts");
		$insert_data['uid'] = $this->get_import->uid($data['authorid']);
		if(!empty($insert_data['uid']))
		{
			$insert_data['username'] = $this->get_import->username($insert_data['import_uid'], $data['author']);
		}
		else
		{
			$insert_data['username'] = encode_to_utf8(utf8_unhtmlentities($data['author']), "forum_post", "posts");
		}
		$insert_data['dateline'] = $data['dateline'];
		$insert_data['message'] = encode_to_utf8($this->bbcode_parser->convert($data['message']), "forum_post", "posts");
		$insert_data['ipaddress'] = my_inet_pton($data['useip']);
		$insert_data['includesig'] = $data['usesig'];
		$insert_data['smilieoff'] = $data['allowsmilie'] == 1 ? 1 : 0;
		if($data['invisible'] == 0)
		{
			$insert_data['visible'] = 1;
		}
		else if($data['invisible'] == -2)
		{
			$insert_data['visible'] = 0;
		}
		else
		{
			if(($data['first'] == 1 && $data['invisible'] == -1) || ($data['first'] == 0 && $data['invisible'] == -5))
			{
				$insert_data['visible'] = -1;
			}
		}
		
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
			$query = $this->old_db->simple_select("forum_post", "COUNT(*) as count");
			$import_session['total_posts'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}
		
		return $import_session['total_posts'];
	}
}


