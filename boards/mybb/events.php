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

class MYBB_Converter_Module_Events extends Converter_Module_Events {

	var $settings = array(
		'friendly_name' => 'events',
		'progress_column' => 'eid',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select("events", "*", "", array('limit_start' => $this->trackers['start_events'], 'limit' => $import_session['events_per_screen']));
		while($event = $this->old_db->fetch_array($query))
		{
			$this->insert($event);
		}
	}

	function convert_data($data)
	{
		global $db;
		static $field_info;

		if(!isset($field_info))
		{
			// Get columns so we avoid any 'unknown column' errors
			$field_info = $db->show_fields_from("events");
		}

		$insert_data = array();

		foreach($field_info as $key => $field)
		{
			if($field['Extra'] == 'auto_increment')
			{
				if($db->type != "sqlite")
				{
					unset($insert_data[$field['Field']]);
				}
				continue;
			}

			if(isset($data[$field['Field']]))
			{
				$insert_data[$field['Field']] = $data[$field['Field']];
			}
		}

		// MyBB 1.8 values
		$insert_data['import_eid'] = $data['eid'];

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of events
		if(!isset($import_session['total_events']))
		{
			$query = $this->old_db->simple_select("events", "COUNT(*) as count");
			$import_session['total_events'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_events'];
	}
}

?>