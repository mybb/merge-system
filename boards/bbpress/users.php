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

class BBPRESS_Converter_Module_Users extends Converter_Module_Users {

	var $settings = array(
		'friendly_name' => 'users',
		'progress_column' => 'ID',
		'encode_table' => 'users',
		'username_column' => 'user_login',
		'email_column' => 'user_email',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session, $db;

		// Get members
		$query = $this->old_db->simple_select("users", "*", "ID > 0", array('limit_start' => $this->trackers['start_users'], 'limit' => $import_session['users_per_screen']));
		while($user = $this->old_db->fetch_array($query))
		{
			$this->insert($user);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// bbPress values
		$insert_data['usergroup'] = $this->board->get_group_id($data['ID'], array("not_multiple" => true));
		$insert_data['displaygroup'] = $insert_data['usergroup'];
		$insert_data['import_displaygroup'] = $insert_data['import_usergroup'];
		$insert_data['import_uid'] = $data['ID'];
		$insert_data['username'] = encode_to_utf8($data['user_login'], "users", "users");
		$insert_data['email'] = $data['user_email'];
		$insert_data['regdate'] = strtotime($data['user_registered']);
		$insert_data['website'] = $data['user_url'];

		$insert_data['lastpost'] = $this->get_user_lastpost($data['ID']);;

		$insert_data['passwordconvert'] = $data['user_pass'];
		$insert_data['passwordconverttype'] = 'bbpress';
		$insert_data['loginkey'] = generate_loginkey();

		return $insert_data;
	}

	function get_user_lastpost($uid)
	{
		$query = $this->old_db->simple_select("usermeta", "COUNT(*) as count", "user_id = '{$uid}'");

		while($metadata = $this->old_db->fetch_array($query))
		{
			if ($metadata['meta_key'] = "last_posted")
			{
				$last = $metadata['meta_value'];
			}
		}

		return $last;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of members
		if(!isset($import_session['total_users']))
		{
			$query = $this->old_db->simple_select("users", "COUNT(*) as count", "ID > 0");
			$import_session['total_users'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_users'];
	}
}

?>