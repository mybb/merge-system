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

class BBPRESS_Converter_Module_Posts extends Converter_Module_Posts {

	var $settings = array(
		'friendly_name' => 'posts',
		'progress_column' => 'ID',
		'default_per_screen' => 1000,
		'check_table_type' => 'posts',
	);

	function import()
	{
		global $import_session;

		$query = $this->old_db->query("SELECT p.*, t.post_parent AS fid, t.post_title AS subject
			FROM ".OLD_TABLE_PREFIX."posts p
			LEFT JOIN ".OLD_TABLE_PREFIX."posts t ON(t.ID=p.post_parent)
			WHERE p.post_type='reply'
			LIMIT {$this->trackers['start_posts']}, {$import_session['posts_per_screen']}");
		while($post = $this->old_db->fetch_array($query))
		{
			$ipquery = $this->old_db->simple_select("postmeta", "meta_value", "meta_key='_bbp_author_ip' AND post_id='{$post['ID']}'");
			$post['ip'] = $this->old_db->fetch_field($ipquery, "meta_value");

			$this->insert($post);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// bbPress values
		$insert_data['import_pid'] = $data['ID'];
		$insert_data['tid'] = $this->get_import->tid($data['post_parent']);
		$insert_data['fid'] = $this->get_import->fid($data['fid']);
		$insert_data['subject'] = encode_to_utf8($this->bbcode_parser->convert_title($data['subject']), "posts", "posts");
		$insert_data['uid'] = $this->get_import->uid($data['post_author']);
		$insert_data['import_uid'] = $data['post_author'];
		$insert_data['username'] = $this->get_import->username($data['post_author']);
		$insert_data['dateline'] = strtotime($data['post_date']);
		$insert_data['message'] = encode_to_utf8($this->bbcode_parser->convert($data['post_content']), "posts", "posts");
		$insert_data['ipaddress'] = my_inet_pton($data['ip']);

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
			$query = $this->old_db->simple_select("posts", "COUNT(*) as count", "post_type='reply'");
			$import_session['total_posts'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_posts'];
	}
}

?>