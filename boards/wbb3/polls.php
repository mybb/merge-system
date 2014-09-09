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

class WBB3_Converter_Module_Polls extends Converter_Module_Polls {

	var $settings = array(
		'friendly_name' => 'polls',
		'progress_column' => 'pollID',
		'default_per_screen' => 1000,
	);

	var $cache_poll_choices = array();

	var $cache_get_poll_thread = array();

	function import()
	{
		global $import_session, $db;

		$done_array = array();

		$query = $this->old_db->simple_select(WCF_PREFIX."poll", "*", "messageType='post'", array('limit_start' => $this->trackers['start_polls'], 'limit' => $import_session['polls_per_screen']));
		while($poll = $this->old_db->fetch_array($query))
		{
			// WBB allows multiple polls per thread (only one per post)
			// As we only allow one per thread we simply copy all polls which were addeed to a first post
			$tquery = $db->simple_select("threads", "tid,import_tid", "import_firstpost='{$poll['messageID']}'");
			if($db->num_rows($tquery) == 0)
			{
				// This poll isn't in a first post
				$this->increment_tracker("polls");
				continue;
			}
			$thread = $db->fetch_array($tquery);
			$db->free_result($tquery);

			$poll = array_merge($poll, $thread);

			$pid = $this->insert($poll);

			// Restore connections
			$db->update_query("threads", array('poll' => $pid), "import_tid = '{$poll['tid']}'");
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// WBB 3 values
		$insert_data['import_tid'] = $data['import_tid'];
		$insert_data['import_pid'] = $data['pollID'];
		$insert_data['tid'] = $data['tid'];

		$insert_data['question'] = $data['question'];
		$insert_data['dateline'] = $data['time'];

		$poll_choices = $this->get_poll_choices($data['pollID']);
		$insert_data['options'] = $poll_choices['options'];
		$insert_data['votes'] = $poll_choices['votes'];
		$insert_data['numoptions'] = $poll_choices['options_count'];
		$insert_data['numvotes'] = $poll_choices['vote_count'];

		// WBB saves the end timestamp, time for some math
		if($data['endTime'] != 0)
		{
			$period = $data['endTime'] - $data['time']; // Timeout in seconds
			$insert_data['timeout'] = (int)($period / (24*3600));
		}

		return $insert_data;
	}

	function get_poll_choices($pid)
	{
		if(array_key_exists($pid, $this->cache_poll_choices))
		{
			return $this->cache_poll_choices[$pid];
		}

		$seperator = '';
		$options = '';
		$votes = '';
		$vote_count = 0;
		$options_count = 0;

		$query = $this->old_db->simple_select(WCF_PREFIX."poll_option", "*", "pollID='{$pid}'", array("order_by" => "showOrder"));
		while($vote_result = $this->old_db->fetch_array($query))
		{
			$options .= $seperator.$vote_result['pollOption'];
			$votes .= $seperator.$vote_result['votes'];
			++$options_count;
			$vote_count += $vote_result['votes'];
			$seperator = '||~|~||';
		}
		$this->old_db->free_result($query);

		$poll_choices = array(
			'options' => $options,
			'votes' => $votes,
			'options_count' => $options_count,
			'vote_count' => $vote_count
		);

		$this->cache_poll_choices[$pid] = $poll_choices;

		return $poll_choices;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of polls
		if(!isset($import_session['total_polls']))
		{
			$query = $this->old_db->simple_select(WCF_PREFIX."poll", "COUNT(*) as count");
			$import_session['total_polls'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_polls'];
	}
}

?>