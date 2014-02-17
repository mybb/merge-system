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

class PHPBB2_Converter_Module_Threads extends Converter_Module_Threads {

	var $settings = array(
		'friendly_name' => 'threads',
		'progress_column' => 'topic_id',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select("topics", "*", "", array('limit_start' => $this->trackers['start_threads'], 'limit' => $import_session['threads_per_screen']));
		while($thread = $this->old_db->fetch_array($query))
		{
			$this->insert($thread);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// phpBB 2 values
		$insert_data['import_tid'] = $data['topic_id'];
		$insert_data['sticky'] = $data['topic_type'];
		$insert_data['fid'] = $this->get_import->fid_f($data['forum_id']);
		$insert_data['import_firstpost'] = $data['topic_first_post_id'];
		$insert_data['dateline'] = $data['topic_time'];
		$insert_data['subject'] = encode_to_utf8(utf8_unhtmlentities($data['topic_title']), "topics", "threads");
		$insert_data['uid'] = $this->get_import->uid($data['topic_poster']);
		$insert_data['import_uid'] = $data['topic_poster'];
		$insert_data['views'] = $data['topic_views'];
		$insert_data['closed'] = $data['topic_status'];
		if($insert_data['closed'] == "no")
		{
			$insert_data['closed'] = '';
		}

		// Shadow topic?
		if($insert_data['closed'] == 2)
		{
			$insert_data['closed'] = 0;
		}

		if($data['topic_moved_id'])
		{
			$insert_data['closed'] .= "|".$this->get_import->tid($data['topic_moved_id']);
		}

		// phpBB 2 has a sticky value of '2' which stands for announcement threads. Our announcements system is seperate.
		if($insert_data['sticky'] > 1)
		{
			$insert_data['sticky'] = 1;
		}

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of threads
		if(!isset($import_session['total_threads']))
		{
			$query = $this->old_db->simple_select("topics", "COUNT(*) as count");
			$import_session['total_threads'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_threads'];
	}
}

?>