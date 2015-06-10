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

class XENFORO_Converter_Module_Pollvotes extends Converter_Module_Pollvotes {

	var $settings = array(
		'friendly_name' => 'poll votes',
		'progress_column' => 'poll_response_id',
		'default_per_screen' => 1000,
	);
	
	var $poll_response_cache = array();

	function import()
	{
		global $import_session;
		
		$query = $this->old_db->query("SELECT v.*, p.response
				FROM ".OLD_TABLE_PREFIX."poll_vote v
				LEFT JOIN ".OLD_TABLE_PREFIX."poll_response p ON(p.poll_response_id=v.poll_response_id)
				LIMIT {$this->trackers['start_pollvotes']}, {$import_session['pollvotes_per_screen']}");				
		while($pollvote = $this->old_db->fetch_array($query))
		{
			$this->insert($pollvote);
		}
	}
	
	function convert_data($data)
	{
		$insert_data = array();
		
		// Xenforo 1 values
		$insert_data['uid'] = $this->get_import->uid($data['user_id']);
		$insert_data['dateline'] = $data['vote_date'];
		$insert_data['voteoption'] = $this->get_vote_id($data['poll_id'], $data['response']);
		$insert_data['pid'] = $this->get_import->pollid($data['poll_id']);
		
		return $insert_data;
	}

	function get_vote_id($pid, $answer)
	{
		// XenForo saves the "responseid" which is an autoincremented column so it ignores the poll.
		// However we increment the id per poll (starts at 1 every time). So we need some magic to get "our" id

		if(!isset($this->poll_response_cache[$pid]) || empty($this->poll_response_cache[$pid]))
		{
			$query = $this->old_db->simple_select("poll", "responses", "poll_id='{$pid}'");
			$responses = unserialize($this->old_db->fetch_field($query, "responses"));
			$this->old_db->free_result($query);

			// This generates an array with mybb_id => response
			foreach($responses as $response)
			{
				$this->poll_response_cache[$pid][] = $response['response'];
			}
		}

		foreach($this->poll_response_cache[$pid] as $id => $response)
		{
			if($response == $answer)
			{
				return $id+1; // As said: we start with 1, not 0
			}
		}

		return false;
	}
	
	function fetch_total()
	{
		global $import_session;
		
		// Get number of poll votes
		if(!isset($import_session['total_pollvotes']))
		{
			$query = $this->old_db->simple_select("poll_vote", "COUNT(*) as count");
			$import_session['total_pollvotes'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}
		
		return $import_session['total_pollvotes'];
	}
}

