<?php
/**
 * MyBB 1.6
 * Copyright 2009 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class PHPBB2_Converter_Module_Polls extends Converter_Module_Polls {

	var $settings = array(
		'friendly_name' => 'polls',
		'progress_column' => 'vote_id',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session, $db;

		$query = $this->old_db->simple_select("vote_desc", "*", "", array('limit_start' => $this->trackers['start_polls'], 'limit' => $import_session['polls_per_screen']));
		while($poll = $this->old_db->fetch_array($query))
		{
			$pid = $this->insert($poll);

			// Restore connections
			$db->update_query("threads", array('poll' => $pid), "import_tid = '".$poll['topic_id']."'");
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// phpBB 2 values
		$insert_data['import_tid'] = $data['topic_id'];
		$insert_data['tid'] = $this->get_import->tid($data['topic_id']);

		$seperator = '';
		$options = '';
		$votes = '';
		$vote_count = 0;
		$options_count = 0;

		$query = $this->old_db->simple_select("vote_results", "*", "vote_id = '{$data['vote_id']}'");
		while($vote_result = $this->old_db->fetch_array($query))
		{
			$options .= $seperator.$vote_result['vote_option_text'];
			$votes .= $seperator.$vote_result['vote_result'];
			++$options_count;
			$vote_count += $vote_result['vote_result'];
			$seperator = '||~|~||';
		}

		$this->old_db->free_result($query);

		$insert_data['question'] = $data['vote_text'];
		$insert_data['dateline'] = $data['vote_start'];
		$insert_data['options'] = $options;
		$insert_data['votes'] = $votes;
		$insert_data['numoptions'] = $options_count;
		$insert_data['numvotes'] = $vote_count;
		$insert_data['import_pid'] = $data['vote_id'];

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of polls
		if(!isset($import_session['total_polls']))
		{
			$query = $this->old_db->simple_select("vote_desc", "COUNT(*) as count");
			$import_session['total_polls'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_polls'];
	}
}

?>