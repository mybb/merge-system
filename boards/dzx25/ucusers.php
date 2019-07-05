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

class DZX25_Converter_Module_Ucusers extends Converter_Module_Users {
	
	var $settings = array(
			'friendly_name' => 'ucusers',
			'progress_column' => 'uid',
			'encode_table' => 'members',
			'username_column' => 'username',
			'email_column' => 'email',
			'default_per_screen' => 3000,
	);
	
	/**
	 * Total users queried from the MyBB Database used in the users module
	 */
	public $total_ucusers = 0;
	
	function __construct($converter_class)
	{
		parent::__construct($converter_class);
		
		// Alter some default values.
		if(defined("DZX25_CONVERTER_USERS_LASTTIME"))
		{
			$this->default_values['lastactive'] = DZX25_CONVERTER_USERS_LASTTIME;
			$this->default_values['lastvisit'] = DZX25_CONVERTER_USERS_LASTTIME;
		}
		$this->default_values['classicpostbit'] = 1;
		$this->default_values['subscriptionmethod'] = 0;	// Changed from 2 to 0 to perform no email being sent by subscriptions.
		$this->default_values['pmnotify'] = 0;	// Changed from 1 to 0 to not notifying by email.
		$this->default_values['pmfolders'] = '0**$%%$1**$%%$2**$%%$3**$%%$4**';	// From 1820, 0 => Inbox, 1 => Unread, 2 => Sent, 3 => Draft, 4 => trash
	}
	
	function import()
	{
		global $import_session;
		
		// Get members
		$query = $this->old_db->simple_select("members", "*", "", array('order_by' => 'uid', 'order_dir' => 'ASC', 'limit_start' => $this->trackers['start_ucusers'], 'limit' => $import_session['ucusers_per_screen']));
		while($user = $this->old_db->fetch_array($query))
		{
			$this->insert($user);
		}
	}
	
	function convert_data($data)
	{
		$insert_data = array();
		
		// Discuz! UCenter values
		$insert_data['import_uid'] = $data['uid'];
		$insert_data['username'] = encode_to_utf8($data['username'], $this->settings['encode_table'], "users");
		// We have user input Chinese characters in the email field.
		$insert_data['email'] = encode_to_utf8($data['email'], $this->settings['encode_table'], "users");
		$insert_data['regdate'] = $data['regdate'];
		$insert_data['lastactive'] = $data['lastlogintime'] == 0 ? $data['regdate'] : $data['lastlogintime'];
		$insert_data['lastvisit'] = $data['lastlogintime'] == 0 ? $data['regdate'] : $data['lastlogintime'];
		if(substr($data['regip'], 0, 1) == "M" || substr($data['regip'], 0, 1) == "h")
		{
			// Manully added user or user ip is hidden.
			$insert_data['regip'] = '';
		}
		else
		{
			$insert_data['regip'] = my_inet_pton($data['regip']);
		}
		$insert_data['lastip'] = my_inet_pton($data['lastloginip']);
		
		$insert_data['passwordconvert'] = $data['password'];
		$insert_data['passwordconvertsalt'] = $data['salt'];
		$insert_data['passwordconverttype'] = 'dzx25';
		
		return $insert_data;
	}
	
	function fetch_total()
	{
		global $import_session;
		
		// Get number of members
		if(!isset($import_session['total_ucusers']))
		{
			$query = $this->old_db->simple_select("members", "COUNT(*) as count");
			$import_session['total_ucusers'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}
		
		return $import_session['total_ucusers'];
	}

	/**
	 * Customized user insertion into database, in order to use the system's internal tracker.
	 *
	 * @param array $data The insert array going into the MyBB database
	 * @return int|bool The new id or false if it's a duplicated user
	 */
	public function insert($data)
	{
		global $db, $output;
		
		if(!$this->check_for_duplicates($data))
		{
			$this->increment_tracker('ucusers');
			return false;
		}
		
		//++$this->total_users;
		++$this->total_ucusers;
		
		$this->debug->log->datatrace('$data', $data);
		
		$output->print_progress("start", $data[$this->settings['progress_column']]);
		
		// Call our currently module's process function
		$data = $this->convert_data($data);
		
		// Avoid wrong lastactive and lastvisit times (mybb sees "0" or "" as currently online)
		// unsetting the value works as the default value above sets it to the current timestamp
		if(empty($data['lastactive']))
		{
			unset($data['lastactive']);
		}
		if(empty($data['lastvisit']))
		{
			unset($data['lastvisit']);
		}
		
		// Should loop through and fill in any values that aren't set based on the MyBB db schema or other standard default values and escape them properly
		$insert_array = $this->prepare_insert_array($data, 'users');
		
		$this->debug->log->datatrace('$insert_array', $insert_array);
		
		$db->insert_query("users", $insert_array);
		$uid = $db->insert_id();
		
		$this->increment_tracker('ucusers');
		
		$output->print_progress("end");
		
		return $uid;
	}
	
	/**
	 * Customized duplicated user checking trying to deal with some UTF-8 enconding problems.
	 *
	 * @param array $data The insert array going into the MyBB database
	 * @return int|bool The new id or false if it's a duplicated user
	 */
	public function check_for_duplicates(&$user)
	{
		global $db, $output, $import_session;

		if(!$this->total_ucusers)
		{
			// Count the total number of users so we can generate a unique id if we have a duplicate user
			$query = $db->simple_select("users", "COUNT(*) as totalusers");
			$this->total_ucusers = $db->fetch_field($query, "totalusers");
			$db->free_result($query);
		}
		
		$username = $user[$this->settings['username_column']];
		$encoded_username = encode_to_utf8($user[$this->settings['username_column']], $this->settings['encode_table'], "users");
		
		// Check for duplicate users
		$where = "username='".$db->escape_string($username)."' OR username='".$db->escape_string($encoded_username)."'";
		$query = $db->simple_select("users", "username,email,uid,postnum", $where, array('limit' => 1));
		$duplicate_user = $db->fetch_array($query);
		$db->free_result($query);
		
		// Using strtolower and my_strtolower to check, instead of in the query, is exponentially faster
		// If we used LOWER() function in the query the index wouldn't be used by MySQL
		if(strtolower($duplicate_user['username']) == strtolower($username) || converter_my_strtolower($duplicate_user['username']) == converter_my_strtolower($encoded_username))
		{
			// Have to check email in UTF-8 format also.
			$encoded_email = encode_to_utf8($user[$this->settings['email_column']], $this->settings['encode_table'], "users");
			$email_pos = empty($duplicate_user['email']) ? 0 : strpos($encoded_email, $duplicate_user['email']);
			if($encoded_email == $duplicate_user['email'] || ($email_pos !== false && $email_pos == 0))
			{
				$output->print_progress("start");
				$output->print_progress("merge_user", array('import_uid' => $user[$this->settings['progress_column']], 'duplicate_uid' => $duplicate_user['uid']));
				
				// No more actions since user data in UCenter is just very basic.
				return false;
			}
			else
			{
				$user[$this->settings['username_column']] = $duplicate_user['username']."_".$import_session['board']."_import".$this->total_ucusers;
			}
		}
		
		return true;
	}
}

