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

class VBULLETIN3_Converter_Module_Polls extends Converter_Module_Polls {

	var $settings = array(
		'friendly_name' => 'polls',
		'progress_column' => 'pollid',
		'default_per_screen' => 1000,
	);

	var $cache_tid_polls = null;

	function import()
	{
		global $import_session, $db;

		$query = $this->old_db->simple_select("poll", "*", "", array('limit_start' => $this->trackers['start_polls'], 'limit' => $import_session['polls_per_screen']));
		while($poll = $this->old_db->fetch_array($query))
		{
			$pid = $this->insert($poll);

			// Restore connections
			$db->update_query("threads", array('poll' => $pid), "import_poll = '".$poll['pollid']."'");
		}
	}

	function convert_data($data)
	{
		global $db;

		$insert_data = array();

		// vBulletin 3 values
		$thread = $this->get_import_tid_poll($data['pollid']);
		$votes = @explode('|||', $data['votes']);

		$insert_data['import_pid'] = $data['pollid'];
		$insert_data['import_tid'] = $thread['import_tid'];
		$insert_data['tid'] = $thread['tid'];
		$insert_data['question'] = $data['question'];
		$insert_data['dateline'] = $data['dateline'];
		$insert_data['options'] = str_replace('|||', '||~|~||', $data['options']);
		$insert_data['votes'] = str_replace('|||', '||~|~||', $data['votes']);
		$insert_data['numoptions'] = $data['numberoptions'];
		$insert_data['numvotes'] = count($votes);
		$insert_data['timeout'] = $data['timeout'];
		$insert_data['multiple'] = $data['multiple'];
		$insert_data['closed'] = int_to_01($data['active']);

		return $insert_data;
	}

	function get_import_tid_poll($import_pid)
	{
		global $db;

		if(!$this->cache_tid_polls)
		{
			$query = $db->simple_select("threads", "tid,import_tid,import_poll", "import_poll != 0");
			while($thread = $db->fetch_array($query))
			{
				$this->cache_tid_polls[$thread['import_poll']] = array('tid' => $thread['tid'], 'import_tid' => $thread['import_tid']);
			}
			$db->free_result($query);
		}

		return $this->cache_tid_polls[$import_pid];
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of polls
		if(!isset($import_session['total_polls']))
		{
			$query = $this->old_db->simple_select("poll", "COUNT(*) as count");
			$import_session['total_polls'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_polls'];
	}
}

?>