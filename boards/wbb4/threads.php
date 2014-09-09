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

class WBB4_Converter_Module_Threads extends Converter_Module_Threads {

	var $settings = array(
		'friendly_name' => 'threads',
		'progress_column' => 'threadID',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select(WBB_PREFIX."thread", "*", "", array('limit_start' => $this->trackers['start_threads'], 'limit' => $import_session['threads_per_screen']));
		while($thread = $this->old_db->fetch_array($query))
		{
			$this->insert($thread);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// WBB 4 values
		$insert_data['import_tid'] = $data['threadID'];
		$insert_data['sticky'] = $data['isSticky'];
		$insert_data['fid'] = $this->get_import->fid($data['boardID']);
		$insert_data['firstpost'] = $data['firstPostID'];
		$insert_data['import_firstpost'] = $data['firstPostID']; // This is saved twice to make the poll things possible
		$insert_data['dateline'] = $data['time'];
		$insert_data['subject'] = encode_to_utf8($data['topic'], WBB_PREFIX."thread", "threads");
		$insert_data['uid'] = $this->get_import->uid($data['userID']);
		$insert_data['username'] = $data['username'];
		$insert_data['import_uid'] = $data['userID'];
		$insert_data['views'] = $data['views'];
		$insert_data['closed'] = $data['isClosed'];
		if($data['isDeleted'])
		{
			$insert_data['visible'] = -1;
		}

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of threads
		if(!isset($import_session['total_threads']))
		{
			$query = $this->old_db->simple_select(WBB_PREFIX."thread", "COUNT(*) as count");
			$import_session['total_threads'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_threads'];
	}
}

?>