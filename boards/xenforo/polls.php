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

class XENFORO_Converter_Module_Polls extends Converter_Module_Polls {

	var $settings = array(
		'friendly_name' => 'polls',
		'progress_column' => 'poll_id',
		'default_per_screen' => 1000,
	);
	
	var $cache_tid_polls = null;

	function import()
	{
		global $import_session, $db;
		
		$query = $this->old_db->simple_select("poll", "*", "content_type='thread'", array('limit_start' => $this->trackers['start_polls'], 'limit' => $import_session['polls_per_screen']));
		while($poll = $this->old_db->fetch_array($query))
		{
			$pid = $this->insert($poll);
			
			// Restore connections
			$db->update_query("threads", array('poll' => $pid), "import_tid = '".$poll['content_id']."'");
		}
	}
	
	function convert_data($data)
	{
		$insert_data = array();

		// Xenforo values
		$responses = unserialize($data['responses']);

		$seperator = '';
		$options = '';
		$votes = '';
		$vote_count = 0;
		$options_count = 0;

		foreach($responses as $response)
		{
			$options .= $seperator.$response['response'];
			$votes .= $seperator.$response['response_vote_count'];
			++$options_count;
			$vote_count += $response['response_vote_count'];
			$seperator = '||~|~||';
		}

		$insert_data['import_pid'] = $data['poll_id'];
		$insert_data['import_tid'] = $data['content_id'];
		$insert_data['tid'] = $this->get_import->tid($data['content_id']);
		$insert_data['question'] = $data['question'];
		$insert_data['dateline'] = TIME_NOW;
		$insert_data['options'] = $options;
		$insert_data['votes'] = $votes;
		$insert_data['numoptions'] = $options_count;
		$insert_data['numvotes'] = $vote_count;
		$insert_data['timeout'] = $data['timeout'];
		$insert_data['multiple'] = $data['multiple'];
		
		// XenForo saves the end timestamp, time for some math
		if($data['close_date'] != 0)
		{
			$period = $data['close_date'] - TIME_NOW; // Timeout in seconds
			$insert_data['timeout'] = (int)($period / (24*3600));
		}
		
		return $insert_data;
	}
	
	function fetch_total()
	{
		global $import_session;
		
		// Get number of polls
		if(!isset($import_session['total_polls']))
		{
			$query = $this->old_db->simple_select("poll", "COUNT(*) as count", "content_type='thread'");
			$import_session['total_polls'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}
		
		return $import_session['total_polls'];
	}
}

?>
