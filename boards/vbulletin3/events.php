<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2011 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class VBULLETIN3_Converter_Module_Events extends Converter_Module_Events {

	var $settings = array(
		'friendly_name' => 'events',
		'progress_column' => 'eventid',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select("event", "*", "", array('limit_start' => $this->trackers['start_events'], 'limit' => $import_session['events_per_screen']));
		while($event = $this->old_db->fetch_array($query))
		{
			$this->insert($event);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// vBulletin 3 values
		$insert_data['import_eid'] = $data['eventid'];
		$insert_data['name'] = encode_to_utf8($data['title'], "event", "events");
		$insert_data['description'] = encode_to_utf8($this->bbcode_parser->convert($data['event']), "event", "events");
		$insert_data['uid'] = $this->get_import->uid($data['userid']);
		$insert_data['dateline'] = $data['dateline'];
		$insert_data['starttime'] = $data['dateline_from'];
		$insert_data['endtime'] = $data['dateline_to'];

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of events
		if(!isset($import_session['total_events']))
		{
			$query = $this->old_db->simple_select("event", "COUNT(*) as count");
			$import_session['total_events'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_events'];
	}
}

?>