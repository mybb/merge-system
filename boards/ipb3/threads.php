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

class IPB3_Converter_Module_Threads extends Converter_Module_Threads {

	var $settings = array(
		'friendly_name' => 'threads',
		'progress_column' => 'tid',
		'default_per_screen' => 1000,
	);

	// TODO: #123
	/*
	var $tobechecked = array(
		'subject',
	);
	 */

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select("topics", "*", "state != 'link'", array('order_by' => 'topic_firstpost', 'order_dir' => 'DESC', 'limit_start' => $this->trackers['start_threads'], 'limit' => $import_session['threads_per_screen']));
		while($thread = $this->old_db->fetch_array($query))
		{
			$this->insert($thread);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// Invision Power Board 3 values
		$insert_data['import_tid'] = $data['tid'];
		$insert_data['sticky'] = $data['pinned'];
		$insert_data['fid'] = $this->get_import->fid($data['forum_id']);
		$insert_data['import_firstpost'] = $data['topic_firstpost'];
		$insert_data['dateline'] = $data['start_date'];
		$insert_data['subject'] = encode_to_utf8($data['title'], "topics", "threads");
		$insert_data['uid'] = $this->get_import->uid($data['starter_id']);
		$insert_data['import_uid'] = $data['starter_id'];
		$insert_data['views'] = $data['views'];
		if($data['state'] != 'open')
		{
			$insert_data['closed'] = 1;
		}
		else
		{
			$insert_data['closed'] = '';
		}

		$insert_data['totalratings'] = $data['topic_rating_total'];
		$insert_data['visible'] = $data['approved'];
		$insert_data['numratings'] = $data['topic_rating_hits'];

		$pids = '';
		$seperator = '';
		$query = $this->old_db->simple_select("posts", "pid", "topic_id = '{$data['tid']}'");
		while($post = $this->old_db->fetch_array($query))
		{
			$pids .= $seperator.$post['pid'];
			$seperator = ', ';
		}
		$this->old_db->free_result($query);

		$insert_data['import_poll'] = $data['poll_state'];

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of threads
		if(!isset($import_session['total_threads']))
		{
			$query = $this->old_db->simple_select("topics", "COUNT(*) as count", "state != 'link'");
			$import_session['total_threads'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_threads'];
	}
}


