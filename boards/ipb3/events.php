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

class IPB3_Converter_Module_Events extends Converter_Module_Events {

	var $settings = array(
		'friendly_name' => 'events',
		'progress_column' => 'event_id',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select("cal_events", "*", "", array('limit_start' => $this->trackers['start_events'], 'limit' => $import_session['events_per_screen']));
		while($event = $this->old_db->fetch_array($query))
		{
			$this->insert($event);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// Invision Power Board 3 values
		$insert_data['import_eid'] = $data['event_id'];
		$insert_data['name'] = encode_to_utf8($data['event_title'], "cal_events", "events");
		$insert_data['uid'] = $this->get_import->uid($data['event_member_id']);
		$insert_data['dateline'] = $data['event_lastupdated'];
		$insert_data['description'] = $data['event_content'];
		$insert_data['private'] = $data['event_private'];
		$insert_data['starttime'] = strtotime($insert_data['event_start_date']);
		$insert_data['endtime'] = strtotime($data['event_end_date']);
		$insert_data['repeats'] = $data['event_recurring'];

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of events
		if(!isset($import_session['total_events']))
		{
			$query = $this->old_db->simple_select("cal_events", "COUNT(*) as count");
			$import_session['total_events'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_events'];
	}
}

?>