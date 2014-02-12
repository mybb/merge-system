<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 *
 * $Id: pollvotes.php 4394 2010-12-14 14:38:21Z ralgith $
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class PHPBB2_Converter_Module_Pollvotes extends Converter_Module_Pollvotes {

	var $settings = array(
		'friendly_name' => 'poll votes',
		'progress_column' => 'vote_id',
	);

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select("vote_voters", "*", "", array('limit_start' => $this->trackers['start_pollvotes'], 'limit' => $import_session['pollvotes_per_screen']));
		while($pollvote = $this->old_db->fetch_array($query))
		{
			$this->insert($pollvote);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// phpBB 2 values
		$insert_pollvote['uid'] = $this->get_import->uid($data['vote_user_id']);
		$insert_pollvote['dateline'] = TIME_NOW;
		$insert_pollvote['pid'] = $this->get_import->pollid($data['vote_id']);

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of poll votes
		if(!isset($import_session['total_pollvotes']))
		{
			$query = $this->old_db->simple_select("vote_voters", "COUNT(*) as count");
			$import_session['total_pollvotes'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_pollvotes'];
	}
}

?>