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

class VANILLA_Converter_Module_Users extends Converter_Module_Users {

	var $settings = array(
		'friendly_name' => 'users',
		'progress_column' => 'UserID',
		'encode_table' => 'user',
		'postnum_column' => 'CountDiscussions',
		'username_column' => 'Name',
		'email_column' => 'Email',
		'default_per_screen' => 1000,
	);

	var $get_private_messages_cache = array();

	function import()
	{
		global $import_session;

		// Get members
		$query = $this->old_db->simple_select("user", "*", "Name != 'System'", array('limit_start' => $this->trackers['start_users'], 'limit' => $import_session['users_per_screen']));
		while($user = $this->old_db->fetch_array($query))
		{
			$this->insert($user);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// Vanilla values
		$insert_data['usergroup'] = $this->board->get_group_id($data['UserID'], array("not_multiple" => true));
		$insert_data['additionalgroups'] = str_replace($insert_data['usergroup'], '', $this->board->get_group_id($data['UserID']));
		$insert_data['displaygroup'] = $insert_data['usergroup'];

		$insert_data['import_usergroup'] = $this->board->get_group_id($data['UserID'], array("not_multiple" => true, "original" => true));
		$insert_data['import_additionalgroups'] = $this->board->get_group_id($data['UserID'], array("original" => true));
		$insert_data['import_displaygroup'] = $insert_data['import_usergroup'];
		$insert_data['import_uid'] = $data['UserID'];
		$insert_data['username'] = encode_to_utf8($data['Name'], "user", "users");
		$insert_data['email'] = $data['Email'];
		$insert_data['regdate'] = strtotime($data['DateInserted']);
		$insert_data['lastactive'] = strtotime($data['DateLastActive']);
		$insert_data['lastvisit'] = $insert_data['lastactive'];

		$birthday = strtotime($data['DateOfBirth']);
		$insert_data['birthday'] = date("j-n-Y", $birthday);
		$insert_data['hideemail'] = !$data['ShowEmail'];
		$insert_data['timezone'] = $data['HourOffset'];
		$insert_data['regip'] = my_inet_pton($data['InsertIPAddress']);
		$insert_data['lastip'] = my_inet_pton($data['LastIPAddress']);
		$insert_data['unreadpms'] = $data['CountUnreadConversations'];
		if($data['HashMethod'] == "Vanilla")
		{
			$insert_data['passwordconvert'] = $data['Password'];
			$insert_data['passwordconverttype'] = 'vanilla';
		}
		$insert_data['loginkey'] = generate_loginkey();

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of members
		if(!isset($import_session['total_users']))
		{
			$query = $this->old_db->simple_select("user", "COUNT(*) as count", "Name != 'System'");
			$import_session['total_users'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_users'];
	}
}

?>