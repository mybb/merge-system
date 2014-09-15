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

class VANILLA_Converter_Module_Threads extends Converter_Module_Threads {

	var $settings = array(
		'friendly_name' => 'threads',
		'progress_column' => 'DiscussionID',
		'default_per_screen' => 1000,
	);

	var $get_poll_pid_cache = array();

	function import()
	{
		global $import_session, $db;

		$query = $this->old_db->simple_select("discussion", "*", "", array('limit_start' => $this->trackers['start_threads'], 'limit' => $import_session['threads_per_screen']));
		while($thread = $this->old_db->fetch_array($query))
		{
			$tid = $this->insert($thread);

			// The thread is the firstpost and isn't saved as extra post - but we do that so create it here
			$post = array(
				"tid"			=> (int)$tid,
				"fid"			=> (int)$this->get_import->fid($thread['CategoryID']),
				"subject"		=> $db->escape_string(encode_to_utf8($this->bbcode_parser->convert_title($thread['Name']), "discussion", "posts")),
				"uid"			=> (int)$this->get_import->uid($thread['InsertUserID']),
				"username"		=> $db->escape_string($this->get_import->username($thread['InsertUserID'])),
				"dateline"		=> (int)strtotime($thread['DateInserted']),
				"message"		=> $db->escape_string(encode_to_utf8($this->bbcode_parser->convert($thread['Body']), "discussion", "posts")),
				"ipaddress"		=> $db->escape_binary(my_inet_pton($thread['InsertIPAddress'])),
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

		// Vanilla values
		$insert_data['import_tid'] = $data['DiscussionID'];
		$insert_data['sticky'] = $data['Announce'];
		$insert_data['fid'] = $this->get_import->fid($data['CategoryID']);
		$insert_data['dateline'] = strtotime($data['DateInserted']);
		$insert_data['subject'] = encode_to_utf8($this->bbcode_parser->convert_title($data['Name']), "discussion", "threads");
		$insert_data['uid'] = $this->get_import->uid($data['InsertUserID']);
		$insert_data['import_uid'] = $data['InsertUserID'];
		$insert_data['views'] = $data['CountViews'];
		$insert_data['closed'] = $data['Closed'];

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of threads
		if(!isset($import_session['total_threads']))
		{
			$query = $this->old_db->simple_select("discussion", "COUNT(*) as count");
			$import_session['total_threads'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_threads'];
	}
}

?>