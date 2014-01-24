<?php
/**
 * MyBB 1.6
 * Copyright � 2009 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
  * License: http://www.mybb.com/about/license
 *
 * $Id: posts.php 4394 2010-12-14 14:38:21Z ralgith $
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class PHPBB3_Converter_Module_Posts extends Converter_Module_Posts {

	var $settings = array(
		'friendly_name' => 'posts',
		'progress_column' => 'post_id',
		'default_per_screen' => 1000,
		'check_table_type' => 'posts',
	);

	function import()
	{
		global $import_session;
		
		$query = $this->old_db->simple_select("posts", "*", "", array('limit_start' => $this->trackers['start_posts'], 'limit' => $import_session['posts_per_screen']));
		while($post = $this->old_db->fetch_array($query))
		{
			$this->insert($post);			
		}
	}
	
	function convert_data($data)
	{
		$insert_data = array();
		
		// phpBB 3 values	
		$insert_data['import_pid'] = $data['post_id'];
		$insert_data['tid'] = $this->get_import->tid($data['topic_id']);

		$insert_data['fid'] = $this->get_import->fid($data['forum_id']);
		$insert_data['subject'] = encode_to_utf8($this->bbcode_parser->convert_title($data['post_subject']), "posts", "posts");
		$insert_data['uid'] = $this->get_import->uid($data['poster_id']);
		$insert_data['import_uid'] = $data['poster_id'];
		$insert_data['username'] = $this->get_import->username($data['poster_id'], $data['post_username']);
		$insert_data['dateline'] = $data['post_time'];
		$insert_data['message'] = encode_to_utf8($this->bbcode_parser->convert($data['post_text'], $data['bbcode_uid']), "posts", "posts");
		$insert_data['ipaddress'] = $data['poster_ip'];
		$insert_data['includesig'] = $data['enable_sig'];
		$insert_data['smilieoff'] = int_to_01($data['enable_smilies']);
		
		return $insert_data;
	}
	
	function test()
	{
		// import_tid => tid
		$this->get_import->cache_tids = array(
			5 => 10,
		);
		
		// import_fid => fid
		$this->get_import->cache_fids = array(
			6 => 11,
		);
		
		// import_uid => uid
		$this->get_import->cache_uids = array(
			7 => 12,
		);
		
		// uid => username
		$this->get_import->cache_usernames = array(
			7 => '#M�gaDeth(b)',
		);
		
		$data = array(
			'post_id' => 1,
			'topic_id' => 5,
			'forum_id' => 6,
			'post_subject' => 'Test�fdfs��',
			'poster_id' => 7,
			'post_time' => 12345678,
			'post_text' => 'Test, test, fdsfdsf ds dsf  est�fdf fdsfds s��',
			'bbcode_uid' => 1,
			'poster_ip' => '127.0.0.1',
			'enable_sig' => 1,
			'enable_smilies' => 1,
		);
		
		$match_data = array(
			'import_pid' => 1,
			'tid' => 10,
			'fid' => 11,
			'subject' => utf8_encode('Test�fdfs��'), // The Merge System should convert the mixed ASCII/Unicode string to proper UTF8
			'uid' => 12,
			'import_uid' => 7,
			'username' => '#M�gaDeth(b)',
			'dateline' => 12345678,
			'message' => utf8_encode('Test, test, fdsfdsf ds dsf  est�fdf fdsfds s��'),
			'ipaddress' => '127.0.0.1',
			'smilieoff' => 0,
		);
		
		$this->assert($data, $match_data);
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
			$query = $this->old_db->simple_select("posts", "COUNT(*) as count");
			$import_session['total_posts'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}
		
		return $import_session['total_posts'];
	}
}

?>
