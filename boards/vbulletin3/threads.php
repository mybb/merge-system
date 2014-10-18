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

class VBULLETIN3_Converter_Module_Threads extends Converter_Module_Threads {

	var $settings = array(
		'friendly_name' => 'threads',
		'progress_column' => 'threadid',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select("thread", "*", "", array('order_by' => 'firstpostid', 'order_dir' => 'ASC', 'limit_start' => $this->trackers['start_threads'], 'limit' => $import_session['threads_per_screen']));
		while($thread = $this->old_db->fetch_array($query))
		{
			$this->insert($thread);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// vBulletin 3 values
		$insert_data['import_tid'] = $data['threadid'];
		$insert_data['sticky'] = $data['sticky'];
		$insert_data['fid'] = $this->get_import->fid($data['forumid']);
		$insert_data['import_firstpost'] = $data['firstpostid'];
		$insert_data['dateline'] = $data['dateline'];
		$insert_data['subject'] = encode_to_utf8(str_replace('&quot;', '"', $data['title']), "thread", "threads");
		if(strlen($insert_data['subject']) > 120)
		{
			$insert_data['subject'] = substr($insert_data['subject'], 0, 117)."...";
		}
		$insert_data['import_poll'] = $data['pollid'];
		$insert_data['uid'] = $this->get_import->uid($data['postuserid']);
		$insert_data['import_uid'] = $data['postuserid'];
		$insert_data['views'] = $data['views'];
		$insert_data['closed'] = int_to_01($data['open']);

		if($insert_data['closed'] == 'no')
		{
			$insert_data['closed'] = '';
		}

		if($data['open'] == '10')
		{
			$insert_data['closed'] = 'moved|'.$this->get_import->tid($data['pollid']);
		}

		$insert_data['totalratings'] = $data['votetotal'];
		$insert_data['notes'] = $data['notes'];
		$insert_data['visible'] = $data['visible'];
		$insert_data['numratings'] = $data['votenum'];
		$insert_data['attachmentcount'] = $data['attach'];

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of threads
		if(!isset($import_session['total_threads']))
		{
			$query = $this->old_db->simple_select("thread", "COUNT(*) as count");
			$import_session['total_threads'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_threads'];
	}
}

?>