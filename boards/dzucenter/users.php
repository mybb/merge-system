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

class DZUCENTER_Converter_Module_Users extends Converter_Module_Users {
	
	var $settings = array(
			'friendly_name' => 'users',
			'progress_column' => 'uid',
			'encode_table' => 'members',
			'username_column' => 'username',
			'email_column' => 'email',
			'default_per_screen' => 3000,
	);
	
	/**
	 * Total users queried from the MyBB Database used in the users module
	 */
	public $total_users = 0;
	
	function __construct($converter_class)
	{
		parent::__construct($converter_class);
		
		// Alter some default values.
		if(defined("DZUCENTER_CONVERTER_USERS_LASTTIME"))
		{
			$this->default_values['lastactive'] = DZUCENTER_CONVERTER_USERS_LASTTIME;
			$this->default_values['lastvisit'] = DZUCENTER_CONVERTER_USERS_LASTTIME;
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
		$query = $this->old_db->simple_select("members", "*", "", array('order_by' => 'uid', 'order_dir' => 'ASC', 'limit_start' => $this->trackers['start_users'], 'limit' => $import_session['users_per_screen']));
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
		if(isset($data['duplicated_username']))
		{
			$insert_data['username'] = $data['duplicated_username'];
		}
		else
		{
			$insert_data['username'] = $this->board->encode_to_utf8($data['username'], $this->settings['encode_table'], "users");
		}
		// We have user input Chinese characters in the email field.
		$insert_data['email'] = $this->board->encode_to_utf8($data['email'], $this->settings['encode_table'], "users");
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
		$insert_data['passwordconverttype'] = 'ucenter';
		
		return $insert_data;
	}
	
	function fetch_total()
	{
		global $import_session;
		
		// Get number of members
		if(!isset($import_session['total_users']))
		{
			$query = $this->old_db->simple_select("members", "COUNT(*) as count");
			$import_session['total_users'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}
		
		return $import_session['total_users'];
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

		if(!$this->total_users)
		{
			// Count the total number of users so we can generate a unique id if we have a duplicate user
			$query = $db->simple_select("users", "COUNT(*) as totalusers");
			$this->total_users = $db->fetch_field($query, "totalusers");
			$db->free_result($query);
		}
		
		$username = $user[$this->settings['username_column']];
		$encoded_username = $this->board->encode_to_utf8($user[$this->settings['username_column']], $this->settings['encode_table'], "users");
		
		// Check for duplicate users
		$where = "username='".$db->escape_string($username)."' OR username='".$db->escape_string($encoded_username)."'";
		$query = $db->simple_select("users", "username,email,uid", $where, array('limit' => 1));
		$duplicate_user = $db->fetch_array($query);
		$db->free_result($query);
		
		// Using strtolower and my_strtolower to check, instead of in the query, is exponentially faster
		// If we used LOWER() function in the query the index wouldn't be used by MySQL
		if(strtolower($duplicate_user['username']) == strtolower($username) || $this->board->converter_my_strtolower($duplicate_user['username']) == $this->board->converter_my_strtolower($encoded_username))
		{
			// Have to check email in UTF-8 format also.
			$encoded_email = $this->board->encode_to_utf8($user[$this->settings['email_column']], $this->settings['encode_table'], "users");
			$email_pos = empty($duplicate_user['email']) ? -1 : strpos($duplicate_user['email'], $encoded_email);
			$email_length = strlen($user[$this->settings['email_column']]);
			if($encoded_email == $duplicate_user['email'] || (defined("DZUCENTER_CONVERTER_USERS_FIX_EMAIL") && DZUCENTER_CONVERTER_USERS_FIX_EMAIL && $email_pos !== false && $email_pos == 0 && $email_length == 32))
			{
				$output->print_progress("start");
				$output->print_progress("merge_user", array('import_uid' => $user[$this->settings['progress_column']], 'duplicate_uid' => $duplicate_user['uid']));
				$db->update_query("users", array('import_uid' => $user[$this->settings['progress_column']]), "uid = '{$duplicate_user['uid']}'");
				
				// No more actions since user data in UCenter is just very basic, and we will not overwrite the passwordconvert* and other fields here.
				return false;
			}
			else
			{
				$user['duplicated_username'] = $duplicate_user['username']."_".$import_session['board']."_import".$this->total_users;
			}
		}
		
		return true;
	}
}


