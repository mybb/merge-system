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

class SMF_Converter_Module_Polls extends Converter_Module_Polls {

	var $settings = array(
		'friendly_name' => 'polls',
		'progress_column' => 'ID_POLL',
		'default_per_screen' => 1000,
	);

	var $cache_poll_choices = array();

	var $cache_get_poll_thread = array();

	function import()
	{
		global $import_session, $db;

		$query = $this->old_db->simple_select("polls", "*", "", array('limit_start' => $this->trackers['start_polls'], 'limit' => $import_session['polls_per_screen']));
		while($poll = $this->old_db->fetch_array($query))
		{
			$pid = $this->insert($poll);

			// Restore connections
			$db->update_query("threads", array('poll' => $pid), "import_poll = '".$poll['ID_POLL']."'");
		}
	}

	function convert_data($data)
	{
		global $db;

		$insert_data = array();

		// SMF values
		$thread = $this->get_poll_thread($data['ID_POLL']);

		$insert_data['import_tid'] = $thread['import_tid'];
		$insert_data['tid'] = $thread['tid'];
		$insert_data['dateline'] = $thread['dateline'];

		$poll_choices = $this->get_poll_choices($data['ID_POLL']);

		$insert_data['question'] = $data['question'];
		$insert_data['options'] = $poll_choices['options'];
		$insert_data['votes'] = $poll_choices['votes'];
		$insert_data['numoptions'] = $poll_choices['options_count'];
		$insert_data['numvotes'] = $poll_choices['vote_count'];
		$insert_data['timeout'] = $data['expireTime'];

		return $insert_data;
	}

	function get_poll_thread($pid)
	{
		global $db;

		if(array_key_exists($pid, $this->cache_get_poll_thread))
		{
			return $this->cache_get_poll_thread[$pid];
		}

		$query = $db->simple_select("threads", "tid,dateline,import_tid", "import_poll = '{$pid}'");
		$thread = $db->fetch_array($query);

		$this->cache_get_poll_thread[$pid] = $thread;

		return $thread;
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

		$query = $this->old_db->simple_select("poll_choices", "*", "ID_POLL = '{$pid}'");
		while($vote_result = $this->old_db->fetch_array($query))
		{
			$options .= $seperator.$this->old_db->escape_string($vote_result['label']);
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
			$query = $this->old_db->simple_select("polls", "COUNT(*) as count");
			$import_session['total_polls'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_polls'];
	}
}

?>