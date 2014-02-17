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

class IPB3_Converter_Module_Polls extends Converter_Module_Polls {

	var $settings = array(
		'friendly_name' => 'polls',
		'progress_column' => 'pid',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session, $db;

		$query = $this->old_db->simple_select("polls", "*", "", array('limit_start' => $this->trackers['start_polls'], 'limit' => $import_session['polls_per_screen']));
		while($poll = $this->old_db->fetch_array($query))
		{
			$pid = $this->insert($poll);

			// Restore connections
			$db->update_query("threads", array('poll' => $pid), "import_tid = '".$poll['tid']."'");
		}
	}

	function convert_data($data)
	{
		global $db;

		$insert_data = array();

		$insert_data['import_tid'] = $data['tid'];
		$insert_data['import_pid'] = $data['pid'];
		$insert_data['tid'] = $this->get_import->tid($data['tid']);
		$choices = unserialize(utf8_decode($data['choices']));
		$choices = $choices[1];

		$seperator = '';
		$choices1 = '';
		$choice_count = 0;
		foreach($choices['choice'] as $key => $choice)
		{
			++$choice_count;
			$choices1 .= $seperator.$db->escape_string($choice);
			$seperator = '||~|~||';
		}

		$seperator = '';
		$votes = '';
		foreach($choices['votes'] as $key => $vote)
		{
			$votes .= $seperator.$vote;
			$seperator = '||~|~||';
		}

		$insert_data['question'] = $choices['question'];
		$insert_data['dateline'] = $data['start_date'];
		$insert_data['options'] = $choices1;
		$insert_data['votes'] = $votes;
		$insert_data['numoptions'] = $choice_count;
		$insert_data['numvotes'] = $data['votes'];
		$insert_data['multiple'] = $choices['multi'];

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of polls
		if(!isset($import_session['total_polls']))
		{
			$query = $this->old_db->simple_select("polls", "COUNT(*) as count");
			$import_session['total_polls'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_polls'];
	}
}

?>