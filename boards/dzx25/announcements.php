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

class DZX25_Converter_Module_Announcements extends Converter_Module {
	
	var $settings = array(
			'friendly_name' => 'announcements',
			'progress_column' => 'id',
			'encode_table' => 'forum_announcement',
			'encode_table_users' => 'common_member',
			'default_per_screen' => 1000,
	);

	public $default_values = array(
			'fid' => -1,
			'uid' => 0,
			'subject' => '',
			'message' => '',
			'startdate' => TIME_NOW,
			'enddate' => 0,
			'allowhtml' => 0,
			'allowmycode' => 0,
			'allowsmilies' => 0,
	);
	
	public $binary_fields = array(
	);
	
	public $integer_fields = array(
			'fid',
			'uid',
			'startdate',
			'enddate',
			'allowhtml',
			'allowmycode',
			'allowsmilies',
	);
	
	function import()
	{
		global $import_session;
		
		// Get members
		$query = $this->old_db->simple_select("forum_announcement", "*", "", array('order_by' => 'id', 'order_dir' => 'ASC', 'limit_start' => $this->trackers['start_announcements'], 'limit' => $import_session['announcements_per_screen']));
		while($announcement = $this->old_db->fetch_array($query))
		{
			$this->insert($announcement);
		}
	}

	public function insert($data)
	{
		global $db, $output;
		
		$this->debug->log->datatrace('$data', $data);
		
		$output->print_progress("start", $data[$this->settings['progress_column']]);
		
		// Call our currently module's process function
		$data = $this->convert_data($data);
		
		// Should loop through and fill in any values that aren't set based on the MyBB db schema or other standard default values and escape them properly
		$insert_array = $this->prepare_insert_array($data, 'announcements');
		
		$this->debug->log->datatrace('$insert_array', $insert_array);
		
		$db->insert_query("announcements", $insert_array);
		$aid = $db->insert_id();
		
		$this->increment_tracker('announcements');
		
		$output->print_progress("end");
		
		return $aid;
	}
	
	function convert_data($data)
	{
		$insert_data = array();
		
		// Discuz! values
		$uid = $this->get_uid($data['author']);
		if($uid !== false)
		{
			$insert_data['uid'] = $uid;
		}
		$insert_data['subject'] = encode_to_utf8($this->bbcode_parser->convert(utf8_unhtmlentities($data['subject'])), $this->settings['encode_table'], "announcements");
		$insert_data['message'] = encode_to_utf8($this->bbcode_parser->convert(utf8_unhtmlentities($data['message'])), $this->settings['encode_table'], "announcements");
		$insert_data['startdate'] = $data['starttime'];
		$insert_data['enddate'] = $data['endtime'];
		
		return $insert_data;
	}
	
	function fetch_total()
	{
		global $import_session;
		
		// Get number of announcements
		if(!isset($import_session['total_announcements']))
		{
			$query = $this->old_db->simple_select("forum_announcement", "COUNT(*) as count");
			$import_session['total_announcements'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}
		
		return $import_session['total_announcements'];
	}
	
	function get_uid($username)
	{
		global $db;
		
		$encoded_username = encode_to_utf8($username, $this->settings['encode_table_users'], "users");
		
		// Check for duplicate users
		$where = "username='".$db->escape_string($username)."' OR username='".$db->escape_string($encoded_username)."'";
		$query = $db->simple_select("users", "username,uid", $where, array('limit' => 1));
		$user = $db->fetch_array($query);
		$db->free_result($query);
		
		// Using strtolower and my_strtolower to check, instead of in the query, is exponentially faster
		// If we used LOWER() function in the query the index wouldn't be used by MySQL
		if(strtolower($user['username']) == strtolower($username) || converter_my_strtolower($user['username']) == converter_my_strtolower($encoded_username))
		{
			return $user['uid'];
		}
		
		return false;
	}
}


