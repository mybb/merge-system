<?php
/**
 * MyBB 1.6
 * Copyright © 2009 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
  * License: http://www.mybb.com/about/license
 *
 * $Id: events.php 4394 2010-12-14 14:38:21Z ralgith $
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class SMF_Converter_Module_Events extends Converter_Module_Events {

	var $settings = array(
		'friendly_name' => 'events',
		'progress_column' => 'ID_EVENT',
		'default_per_screen' => 1000,
	);
	
	var $threads_cache = array();

	function import()
	{
		global $import_session;
		
		$query = $this->old_db->simple_select("calendar", "*", "", array('limit_start' => $this->trackers['start_events'], 'limit' => $import_session['events_per_screen']));
		while($event = $this->old_db->fetch_array($query))
		{
			$this->insert($event);
		}
	}
	
	function convert_data($data)
	{
		$insert_data = array();
		
		// SMF values
		$insert_data['import_eid'] = $data['ID_EVENT'];
		$insert_data['uid'] = $this->get_import->uid($data['ID_MEMBER']);
		$insert_data['name'] = $data['title'];
		
		// M-d-Y
		$start_days = explode('-', $data['startDate']);
		$end_days = explode('-', $data['endDate']);
		$insert_data['dateline'] = mktime(0, 0, 0, $start_days[1], $start_days[2], $start_days[0]);
		$insert_data['starttime'] = $insert_data['dateline'];
		$insert_data['endtime'] = mktime(0, 0, 0, $end_days[1], $end_days[2], $end_days[0]);
						
		$thread = $this->get_thread($data['ID_TOPIC']);				
		$insert_data['description'] = $thread['body'];
		
		return $insert_data;
	}
	
	function test()
	{
		// import_uid -> uid
		$this->get_import->cache_uids = array(
			5 => 10,
		);
		
		// topic id -> array of thread info
		$this->threads_cache = array(
			6 => array(
				'body' => 'Test, test, stéfdfdsfsf fdsfds sÿÿ',
			),
		);
		
		$data = array(
			'ID_EVENT' => 4,
			'ID_MEMBER' => 5,
			'title' => 'Test, test, stéfdf fdsfds sÿÿ',
			'startDate' => '4-27-1992',
			'endDate' => '4-27-2010',
			'ID_TOPIC' => 6,
		);
		
		$match_data = array(
			'import_eid' => 4,
			'uid' => 10,
			'name' => 'Test, test, stéfdf fdsfds sÿÿ',
			'dateline' => '1313218800',
			'starttime' => '1313218800',
			'endtime' => '1314774000',
			'description' => 'Test, test, stéfdfdsfsf fdsfds sÿÿ',
		);
		
		$this->assert($data, $match_data);
	}
	
	/**
	 * Get a thread from the SMF database
	 *
	 * @param int Thread ID
	 * @return array The thread
	 */
	function get_thread($tid)
	{
		if(array_key_exists($tid, $this->threads_cache))
		{
			return $this->threads_cache[$tid];
		}
		$tid = intval($tid);
		$query = $this->old_db->simple_select("topics", "ID_FIRST_MSG", "ID_TOPIC = '{$tid}'", array('limit' => 1));
		$firstpost = $this->board->get_post($this->old_db->fetch_field($query, "ID_FIRST_MSG"));
		
		$this->old_db->free_result($query);
		
		$this->threads_cache[$tid] = $firstpost;
		
		return $firstpost;
	}
	
	function fetch_total()
	{
		global $import_session;
		
		// Get number of events
		if(!isset($import_session['total_events']))
		{
			$query = $this->old_db->simple_select("calendar", "COUNT(*) as count");
			$import_session['total_events'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}
		
		return $import_session['total_events'];
	}
}

?>