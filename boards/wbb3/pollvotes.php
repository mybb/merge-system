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

class WBB3_Converter_Module_Pollvotes extends Converter_Module_Pollvotes {

	var $settings = array(
		'friendly_name' => 'poll votes',
		'progress_column' => 'topic_id',
		'default_per_screen' => 1000,
	);

	var $cache_poll_details = array();
	var $pid_cache;

	function pre_setup()
	{
		global $db;

		$query = $db->simple_select("polls", "pid, import_pid, dateline", "import_pid!='0'");
		while($poll = $db->fetch_array($query))
		{
			$this->pid_cache[$poll['import_pid']] = array("pid" => $poll['pid'], "dateline" => $poll['dateline']);
		}
	}

	function import()
	{
		global $import_session, $db;

		$query = $this->old_db->query("SELECT v.*, o.showOrder
			FROM ".WCF_PREFIX."poll_option_vote v
			LEFT JOIN ".WCF_PREFIX."poll_option o ON(o.pollOptionID=v.pollOptionID)
			WHERE v.pollID IN('".implode("','", array_keys($this->pid_cache))."')
			LIMIT {$this->trackers['start_pollvotes']}, {$import_session['pollvotes_per_screen']}");

       	while($pollvote = $this->old_db->fetch_array($query))
		{
			$this->insert($pollvote);
		}
	}

	function convert_data($data)
	{
		global $db;

		$insert_data = array();

		// WBB 3 values
		$insert_data['pid'] = $this->pid_cache[$data['pollID']]['pid'];
		$insert_data['uid'] = $this->get_import->uid($data['userID']);
		$insert_data['dateline'] = $this->pid_cache[$data['pollID']]['dateline'];
		$insert_data['voteoption'] = $data['showOrder']+1; // WBB starts with 0, we start with 1, so +1

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of poll votes
		if(!isset($import_session['total_pollvotes']))
		{
			// Make sure our cache is up to date
			if(empty($this->pid_cache))
			{
				$this->pre_setup();
			}
			$query = $this->old_db->simple_select(WCF_PREFIX."poll_option_vote", "COUNT(*) AS count", "pollID IN('".implode("','", array_keys($this->pid_cache))."')");
			$import_session['total_pollvotes'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_pollvotes'];
	}
}

?>