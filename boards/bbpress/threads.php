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
		'progress_column' => 'topic_id',
		'default_per_screen' => 1000,
	);

	var $get_poll_pid_cache = array();

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select("topics", "*", "topic_status != '1'", array('order_by' => 'topic_id', 'order_dir' => 'ASC', 'limit_start' => $this->trackers['start_threads'], 'limit' => $import_session['threads_per_screen']));
		while($thread = $this->old_db->fetch_array($query))
		{
			$this->insert($thread);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// bbPress values
		$insert_data['import_tid'] = $data['topic_id'];
		$insert_data['sticky'] = $data['topic_sticky'];
		$insert_data['fid'] = $this->get_import->fid($data['forum_id']);
		$insert_data['dateline'] = strtotime($data['topic_start_time']);
		$insert_data['subject'] = encode_to_utf8($this->bbcode_parser->convert_title($data['topic_title']), "topics", "threads");
		$insert_data['uid'] = $this->get_import->uid($data['topic_poster']);
		$insert_data['import_uid'] = $data['topic_poster'];
		$insert_data['closed'] = '0';
		if ($data['topic_open'] = '0')
		{
			$insert_data['closed'] = '1';
		}

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of threads
		if(!isset($import_session['total_threads']))
		{
			$query = $this->old_db->simple_select("topics", "COUNT(*) as count", "topic_status != '1'");
			$import_session['total_threads'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_threads'];
	}
}

?>