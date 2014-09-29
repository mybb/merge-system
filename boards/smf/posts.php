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

class SMF_Converter_Module_Posts extends Converter_Module_Posts {

	var $settings = array(
		'friendly_name' => 'posts',
		'progress_column' => 'ID_MSG',
		'default_per_screen' => 1000,
		'check_table_type' => 'messages',
	);

	var $cache_first_posts = array();

	function import()
	{
		global $import_session, $db;

		$query = $this->old_db->simple_select("messages", "*", "", array('limit_start' => $this->trackers['start_posts'], 'limit' => $import_session['posts_per_screen']));
		while($post = $this->old_db->fetch_array($query))
		{
			$pid = $this->insert($post);

			$db->update_query("threads", array("firstpost" => $pid), "import_firstpost = '".$post['ID_MSG']."'");
		}
	}

	function convert_data($data)
	{
		global $db;

		$insert_data = array();

		// SMF values
		$insert_data['import_pid'] = $data['ID_MSG'];
		$insert_data['tid'] = $this->get_import->tid($data['ID_TOPIC']);

		// Find if this is the first post in thread
		$first_post = $this->cache_first_post($data['ID_TOPIC']);

		// Make the replyto the first post of thread unless it is the first post
		if($first_post == $data['ID_MSG'])
		{
			$insert_data['replyto'] = 0;
		}
		else
		{
			$insert_data['replyto'] = $this->get_import->pid($first_post);
		}

		$insert_data['fid'] = $this->get_import->fid($data['ID_BOARD']);
		$insert_data['subject'] = encode_to_utf8(utf8_unhtmlentities($data['subject']), "messages", "posts");
		$insert_data['uid'] = $this->get_import->uid($data['ID_MEMBER']);
		$insert_data['import_uid'] = $data['ID_MEMBER'];
		$insert_data['username'] = $data['posterName'];
		$insert_data['dateline'] = $data['posterTime'];
		$insert_data['message'] = encode_to_utf8($this->bbcode_parser->convert(utf8_unhtmlentities($data['body'])), "messages", "posts");
		$insert_data['ipaddress'] = my_inet_pton($data['posterIP']);

		if($data['smileysEnabled'] == '1')
		{
			$insert_data['smilieoff'] = 0;
		}
		else
		{
			$insert_data['smilieoff'] = 1;
		}

		// Get edit name
		if(!empty($data['modifiedName']))
		{
			$query = $db->simple_select("users", "uid", "username='".$db->escape_string($data['modifiedName'])."'", array('limit' => 1));
			$insert_data['edituid'] = $db->fetch_field($query, "uid");
			$db->free_result($query);
		}
		else
		{
			$insert_data['edituid'] = 0;
		}

		$insert_data['edittime'] = $data['modifiedTime'];

		return $insert_data;
	}

	function after_insert($data, $insert_data, $pid)
	{
		global $db;

		// Is this the first post in a thread? Update the reply to value in the thread
		if($insert_data['replyto'] != 0)
		{
			$db->update_query("threads", array('replyto' => $this->get_import->pid($data['ID_MSG'])), "tid='{$insert_data['tid']}'");
		}

		$update_post['message'] = $db->escape_string(str_replace(array("[bgcolor=", "[/bgcolor]"), array("[color=", "[/color]"), preg_replace('#\[quote author\=(.*?) link\=topic\=([0-9]*).msg([0-9]*)\#msg([0-9]*) date\=(.*?)\]#i', "[quote='$1' pid='{$pid}' dateline='$5']", $insert_post['message'])));
		$db->update_query("posts", $update_post, "pid='{$pid}'");
	}

	function cache_first_post($tid)
	{
		global $db;

		if(array_key_exists($tid, $this->cache_first_posts))
		{
			return $this->cache_first_posts[$tid];
		}

		$query = $this->old_db->simple_select("topics", "ID_FIRST_MSG", "ID_TOPIC = '{$tid}'", array('limit' => 1));
		$first_post = $this->old_db->fetch_field($query, "ID_FIRST_MSG");
		$this->old_db->free_result($query);

		$this->cache_first_posts[$tid] = $first_post;

		return $first_post;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of posts
		if(!isset($import_session['total_posts']))
		{
			$query = $this->old_db->simple_select("messages", "COUNT(*) as count");
			$import_session['total_posts'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_posts'];
	}
}

?>