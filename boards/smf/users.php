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

class SMF_Converter_Module_Users extends Converter_Module_Users {

	var $settings = array(
		'friendly_name' => 'users',
		'progress_column' => 'ID_MEMBER',
		'encode_table' => 'members',
		'postnum_column' => 'posts',
		'username_column' => 'memberName',
		'email_column' => 'emailAddress',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;

		// Get members
		$query = $this->old_db->simple_select("members", "*", "", array('order_by' => 'ID_MEMBER', 'order_dir' => 'ASC', 'limit_start' => $this->trackers['start_users'], 'limit' => $import_session['users_per_screen']));
		while($user = $this->old_db->fetch_array($query))
		{
			$this->insert($user);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// SMF values
		$insert_data['usergroup'] = $this->board->get_group_id($data['ID_GROUP'], true, $data['is_activated']);
		$insert_data['additionalgroups'] = $this->board->get_group_id($data['additionalGroups']);
		$insert_data['displaygroup'] = $insert_data['usergroup'];
		$insert_data['import_usergroup'] = $data['ID_GROUP'];
		$insert_data['import_additionalgroups'] = $data['additionalGroups'];
		$insert_data['import_displaygroup'] = $data['ID_GROUP'];
		$insert_data['import_uid'] = $data['ID_MEMBER'];
		$insert_data['username'] = encode_to_utf8($data['memberName'], "members", "users");
		$insert_data['email'] = $data['emailAddress'];
		$insert_data['regdate'] = $data['dateRegistered'];
		$insert_data['lastactive'] = $data['lastLogin'];
		$insert_data['lastvisit'] = $data['lastLogin'];
		$insert_data['website'] = $data['websiteUrl'];
		$last_post = $this->get_last_post($data['ID_MEMBER']);
		$insert_data['lastpost'] = $last_post['posterTime'];
		$data['birthdate'] = trim($data['birthdate']);
		if(!empty($data['birthdate']))
		{
			$insert_data['birthday'] = date("j-n-Y", strtotime($data['birthdate']));
		}
		$insert_data['icq'] = $data['ICQ'];
		$insert_data['aim'] = $data['AIM'];
		$insert_data['yahoo'] = $data['YIM'];
		$insert_data['hideemail'] = $data['hideEmail'];
		$insert_data['invisible'] = int_to_01($data['showOnline']);
		$insert_data['pmnotify'] = $data['pm_email_notify'];
		$insert_data['dateformat'] = get_date_format($data['timeFormat'], "%");
		$insert_data['timeformat'] = get_time_format($data['timeFormat'], "%");
		$insert_data['timezone'] = $data['timeOffset'];
		$insert_data['timezone'] = str_replace(array('.0', '.00'), array('', ''), $insert_data['timezone']);
		$insert_data['buddylist'] = $data['buddy_list'];
		$insert_data['ignorelist'] = $data['pm_ignore_list'];
		$insert_data['regip'] = my_inet_pton($data['memberIP']);
		$insert_data['timeonline'] = $data['totalTimeLoggedIn'];
		$insert_data['totalpms'] = $data['instantMessages'];
		$insert_data['unreadpms'] = $data['unreadMessages'];
		$insert_data['signature'] = str_replace(array("[bgcolor=", "[/bgcolor]"), array("[color=", "[/color]"), preg_replace('#\[quote author\=(.*?) link\=topic\=([0-9]*).msg([0-9]*)\#msg([0-9]*) date\=(.*?)\]#i', "[quote='$1' pid='{$pid}' dateline='$5']", encode_to_utf8($data['signature'], "members", "users")));

		if($data['passwd'])
		{
			$insert_data['passwordconvert'] = $data['passwd'];
		}
		else if($data['password'])
		{
			$insert_data['passwordconvert'] = $data['password'];
		}

		$insert_data['passwordconverttype'] = 'smf11';

		return $insert_data;
	}

	/**
	 * Gets the time of the last post of a user from the SMF database
	 *
	 * @param int User ID
	 * @return int Last post
	 */
	function get_last_post($uid)
	{
		$uid = intval($uid);
		$query = $this->old_db->simple_select("messages", "*", "ID_MEMBER = '{$uid}'", array('order_by' => 'posterTime', 'order_dir' => 'ASC', 'limit' => 1));
		$result = $this->old_db->fetch_array($query);
		$this->old_db->free_result($query);

		return $result;
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
}

?>