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

class BBPRESS_Converter_Module_Threads extends Converter_Module_Threads {

	var $settings = array(
		'friendly_name' => 'threads',
		'progress_column' => 'ID',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session, $db;

		$query = $this->old_db->simple_select("posts", "*", "post_type='topic'", array('order_by' => 'ID', 'order_dir' => 'ASC', 'limit_start' => $this->trackers['start_threads'], 'limit' => $import_session['threads_per_screen']));
		while($thread = $this->old_db->fetch_array($query))
		{
			$ipquery = $this->old_db->simple_select("postmeta", "meta_value", "meta_key='_bbp_author_ip' AND post_id='{$thread['ID']}'");
			$thread['ip'] = $this->old_db->fetch_field($ipquery, "meta_value");
			$tid = $this->insert($thread);

			// The thread is the firstpost and isn't saved as extra post - but we do that so create it here
			$post = array(
				"tid"			=> (int)$tid,
				"fid"			=> (int)$this->get_import->fid($thread['post_parent']),
				"subject"		=> $db->escape_string(encode_to_utf8($this->bbcode_parser->convert_title($thread['post_title']), "posts", "threads")),
				"uid"			=> (int)$this->get_import->uid($thread['post_author']),
				"username"		=> $db->escape_string($this->get_import->username($thread['post_author'])),
				"dateline"		=> (int)strtotime($thread['post_date']),
				"message"		=> $db->escape_string(encode_to_utf8($this->bbcode_parser->convert($thread['post_content']), "posts", "posts")),
				"ipaddress"		=> $db->escape_binary(my_inet_pton($thread['ip'])),
				"includesig"	=> 1,
				"visible"		=> 1
			);
			$this->debug->log->datatrace('$post', $post);
			$db->insert_query("posts", $post);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// bbPress values
		$insert_data['import_tid'] = $data['ID'];
		$insert_data['fid'] = $this->get_import->fid($data['post_parent']);
		$insert_data['dateline'] = strtotime($data['post_date']);
		$insert_data['subject'] = encode_to_utf8($this->bbcode_parser->convert_title($data['post_title']), "posts", "threads");
		$insert_data['uid'] = $this->get_import->uid($data['post_author']);
		$insert_data['import_uid'] = $data['post_author'];

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of threads
		if(!isset($import_session['total_threads']))
		{
			$query = $this->old_db->simple_select("posts", "COUNT(*) as count", "post_type='topic'");
			$import_session['total_threads'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_threads'];
	}
}

?>