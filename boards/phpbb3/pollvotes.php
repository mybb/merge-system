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

class PHPBB3_Converter_Module_Pollvotes extends Converter_Module_Pollvotes {

	var $settings = array(
		'friendly_name' => 'poll votes',
		'progress_column' => 'topic_id',
		'default_per_screen' => 1000,
	);

	var $cache_poll_details = array();

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select("poll_votes", "*", "", array('limit_start' => $this->trackers['start_pollvotes'], 'limit' => $import_session['pollvotes_per_screen']));
		while($pollvote = $this->old_db->fetch_array($query))
		{
			$this->insert($pollvote);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// phpBB 3 values
		$poll = $this->get_poll_details($data['topic_id']);

		$insert_data['uid'] = $this->get_import->uid($data['vote_user_id']);
		$insert_data['dateline'] = $poll['dateline'];
		$insert_data['voteoption'] = $data['poll_option_id'];
		$insert_data['pid'] = $poll['poll'];

		return $insert_data;
	}

	function get_poll_details($tid)
	{
		global $db;

		if(array_key_exists($tid, $this->cache_poll_details))
		{
			return $this->cache_poll_details[$tid];
		}

		$query = $db->simple_select("threads", "dateline,poll", "tid = '".$this->get_import->tid($tid)."'");
		$poll = $db->fetch_array($query);
		$db->free_result($query);

		$this->cache_poll_details[$tid] = $poll;

		return $poll;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of poll votes
		if(!isset($import_session['total_pollvotes']))
		{
			$query = $this->old_db->simple_select("poll_votes", "COUNT(*) as count");
			$import_session['total_pollvotes'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_pollvotes'];
	}
}


