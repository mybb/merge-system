<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2019 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class XENFORO2_Converter_Module_Threads extends Converter_Module_Threads
{
	var $settings = array(
		'friendly_name' => 'threads',
		'progress_column' => 'thread_id',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;
		
		$query = $this->old_db->simple_select("thread", "*", "", array('limit_start' => $this->trackers['start_threads'], 'limit' => $import_session['threads_per_screen']));
		while($thread = $this->old_db->fetch_array($query))
		{
			$this->insert($thread);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// Xenforo 2 values
		$insert_data['import_tid'] = $data['thread_id'];
		$insert_data['fid'] = $this->get_import->fid($data['node_id']);
		$insert_data['import_firstpost'] = $data['first_post_id'];
		$insert_data['dateline'] = $data['post_date'];
		$insert_data['subject'] = encode_to_utf8($data['title'], "thread", "threads");
		$insert_data['uid'] = $this->get_import->uid($data['user_id']);
		$insert_data['username'] = encode_to_utf8($data['username'], "thread", "threads");
		$insert_data['import_uid'] = $data['user_id'];
		$insert_data['views'] = $data['view_count'];
		$insert_data['replies'] = $data['reply_count'];

		$insert_data['closed'] = int_to_01($data['discussion_open']);
		$insert_data['sticky'] = $data['sticky'];

		// Moved thread.
		if($data['discussion_type'] == "redirect")
		{
			$redirect_info = $this->xf_get_redirect($data['thread_id']);
			if($redirect_info !== false)
			{
				$insert_data['closed'] = "moved|".$redirect_info['thread_id'];
				$insert_data['deletetime'] = $redirect_info['expiry_date'];
			}
		}

		// Visibility.
		if($data['discussion_state'] == "deleted")
		{
			// Soft deleted.
			$insert_data['visible'] = -1;
		}
		else if($data['discussion_state'] == "moderated")
		{
			// Moderated, might be equivalent to unapproved in MyBB.
			$insert_data['visible'] = 0;
		}

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
	
	function xf_get_redirect($thread_id)
	{
		$query = $this->old_db->simple_select("thread_redirect", "redirect_key,expiry_date", "", array("limit" => 1));
		$redirect = $this->old_db->fetch_array($query);
		$this->old_db->free_result($query);
		
		$redirect_kyes = explode("-", $redirect['redirect_key']);
		if($redirect_kyes[0] != "thread")
		{
			return false;
		}
		$redirect_info = array(
			"thread_id" => $redirect_kyes[1],
			"thread_fid" => $redirect_kyes[2],
			"expiry_date" => $redirect['expiry_date'],
			"data" => $redirect,
		);
		
		return $redirect_info;
	}
}

