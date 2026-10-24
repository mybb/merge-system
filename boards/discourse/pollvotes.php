<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 *
 * Discourse Poll Votes Converter
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class DISCOURSE_Converter_Module_Pollvotes extends Converter_Module_Pollvotes {

	var $settings = array(
		'friendly_name' => 'poll votes',
		'progress_column' => 'poll_id',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session, $db;

		if(!$this->old_db->table_exists("poll_votes"))
		{
			$import_session['total_pollvotes'] = 0;
			return;
		}

		$query = $this->old_db->simple_select("poll_votes", "*", "", array('limit_start' => $this->trackers['start_pollvotes'], 'limit' => $import_session['pollvotes_per_screen']));
		while($vote = $this->old_db->fetch_array($query))
		{
			$this->insert($vote);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		$insert_data['pid'] = $this->get_import->pollid($data['poll_id']);
		$insert_data['uid'] = $this->get_import->uid($data['user_id']);
		$insert_data['voteoption'] = $data['poll_option_id'];

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		if(!isset($import_session['total_pollvotes']))
		{
			if(!$this->old_db->table_exists("poll_votes"))
			{
				$import_session['total_pollvotes'] = 0;
			}
			else
			{
				$query = $this->old_db->simple_select("poll_votes", "COUNT(*) as count");
				$import_session['total_pollvotes'] = $this->old_db->fetch_field($query, 'count');
				$this->old_db->free_result($query);
			}
		}

		return $import_session['total_pollvotes'];
	}
}
