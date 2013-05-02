<?php
/**
 * MyBB 1.6
 * Copyright 2009 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 * $Id$
 * Modified for Mingle Forums 1.0 by http://www.communityplugins.com
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class MINGLE_Converter_Module_Threads extends Converter_Module_Threads {

	var $settings = array(
		'friendly_name' => 'threads',
		'progress_column' => 'id',
		'default_per_screen' => 1000,
	);
	
	var $get_poll_pid_cache = array();

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select("forum_threads", "*", "1=1", array('order_by' => 'id', 'order_dir' => 'ASC', 'limit_start' => $this->trackers['start_threads'], 'limit' => $import_session['threads_per_screen']));
		while($thread = $this->old_db->fetch_array($query))
		{
			$this->insert($thread);		

			// TODO: thread subscriptions? use wp_options.option_name = mf_thread_subscriptions_{tid} and lookup users based on list of emails

		}
	}
	
	function convert_data($data)
	{
		$insert_data = array();
		
		// bbPress values
		$insert_data['import_tid'] = $data['id'];
		
		$insert_data['sticky'] = ($data['status'] == 'sticky' ? 1 : 0);
		$insert_data['closed'] = $data['closed'];
		
		$insert_data['fid'] = $this->get_import->fid($data['parent_id']);
		$insert_data['dateline'] = strtotime($data['date']);
		$insert_data['subject'] = encode_to_utf8($this->bbcode_parser->convert_title($data['subject']), "forum_threads", "threads");
		$insert_data['uid'] = $this->get_import->uid($data['starter']);
		$insert_data['import_uid'] = $data['starter'];
		$insert_data['views'] = $data['views'];
		
		return $insert_data;
	}
	
	function fetch_total()
	{
		global $import_session;
		
		// Get number of threads
		if(!isset($import_session['total_threads']))
		{
			$query = $this->old_db->simple_select("forum_threads", "COUNT(*) as count", "1=1");
			$import_session['total_threads'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}
		
		return $import_session['total_threads'];
	}

}

?>
