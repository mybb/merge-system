<?php
/**
 * MyBB 1.6
 * Copyright © 2009 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
  * License: http://www.mybb.com/about/license
 *
 * $Id: threads.php 4394 2010-12-14 14:38:21Z ralgith $
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class FLUXBB_Converter_Module_Threads extends Converter_Module_Threads {

	var $settings = array(
		'friendly_name' => 'threads',
		'progress_column' => 'id',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;
		
		$query = $this->old_db->simple_select("topics", "*", "", array('limit_start' => $this->trackers['start_threads'], 'limit' => $import_session['threads_per_screen']));
		while($thread = $this->old_db->fetch_array($query))
		{
			$this->insert($thread);
		}
	}
	
	function convert_data($data)
	{
		$insert_data = array();
		
		// fluxBB values
		$insert_data['import_tid'] = $data['id'];
		$insert_data['sticky'] = $data['sticky'];
		$insert_data['fid'] = $this->get_import->fid_f($data['forum_id']);
		$insert_data['import_firstpost'] = $this->get_first_post($data['id']);
		$insert_data['dateline'] = $data['posted'];
		$insert_data['subject'] = encode_to_utf8($data['subject'], "topics", "threads");
		
		$user = $this->board->get_user($data['poster']);
		
		$insert_data['uid'] = $this->get_import->uid($user['id']);
		$insert_data['import_uid'] = $user['id'];
		$insert_data['views'] = $data['num_views'];
		$insert_data['closed'] = $data['closed'];
		if($insert_data['closed'] == "no")
		{
			$insert_data['closed'] = '';
		}
		
		return $insert_data;
	}
	
	/**
	 * Gets the pid of the first post of a thread from the fluxBB database
	 *
	 * @param int Thread ID
	 * @return integer first post id
	 */
	function get_first_post($tid)
	{
		$query = $this->old_db->simple_select("posts", "*", "topic_id = '{$tid}'", array('order_by' => 'posted', 'order_dir' => 'ASC', 'limit' => 1));
		return $this->old_db->fetch_field($query, "id");
	}
	
	function fetch_total()
	{
		global $import_session;
		
		// Get number of threads
		if(!isset($import_session['total_threads']))
		{
			$query = $this->old_db->simple_select("topics", "COUNT(*) as count");
			$import_session['total_threads'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}
		
		return $import_session['total_threads'];
	}
}

?>