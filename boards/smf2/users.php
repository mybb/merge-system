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

class SMF2_Converter_Module_Users extends Converter_Module_Users {

	var $settings = array(
		'friendly_name' => 'users',
		'progress_column' => 'id_member',
		'encode_table' => 'members',
		'postnum_column' => 'posts',
		'username_column' => 'member_name',
		'email_column' => 'email_address',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;

		// Get members
		$query = $this->old_db->simple_select("members", "*", "", array('order_by' => 'id_member', 'order_dir' => 'ASC', 'limit_start' => $this->trackers['start_users'], 'limit' => $import_session['users_per_screen']));
		while($user = $this->old_db->fetch_array($query))
		{
			$this->insert($user);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// SMF values
		$insert_data['usergroup'] = $this->board->get_group_id($data['id_group'], true, $data['is_activated']);
		$insert_data['additionalgroups'] = $this->board->get_group_id($data['additional_groups']);
		$insert_data['displaygroup'] = $insert_data['usergroup'];
		$insert_data['import_usergroup'] = $data['id_group'];
		$insert_data['import_additionalgroups'] = $data['additional_groups'];
		$insert_data['import_displaygroup'] = $data['id_group'];
		$insert_data['import_uid'] = $data['id_member'];
		$insert_data['username'] = encode_to_utf8($data['member_name'], "members", "users");
		$insert_data['email'] = $data['email_address'];
		$insert_data['regdate'] = $data['date_registered'];
		$insert_data['lastactive'] = $data['last_login'];
		$insert_data['lastvisit'] = $data['last_login'];
		$insert_data['website'] = $data['website_url'];
		$insert_data['avatar'] = $data['avatar'];
		list($width, $height) = @getimagesize($data['avatar']);
		$insert_data['avatardimensions'] = $width.'|'.$height;
		if($insert_data['avatar'] == '')
		{
			$insert_data['avatartype'] = "";
		}
		else
		{
			$insert_data['avatartype'] = 'remote';
		}
		$last_post = $this->get_last_post($data['id_member']);
		$insert_data['lastpost'] = isset($last_post['poster_time']) ? $last_post['poster_time'] : 0;
		$data['birthdate'] = trim($data['birthdate']);
		if(!empty($data['birthdate']))
		{
			$insert_data['birthday'] = date("j-n-Y", strtotime($data['birthdate']));
		}
		$insert_data['icq'] = $data['icq'];
		$insert_data['aim'] = $data['aim'];
		$insert_data['yahoo'] = $data['yim'];
		$insert_data['hideemail'] = $data['hide_email'];
		$insert_data['invisible'] = int_to_01($data['show_online']);
		$insert_data['pmnotify'] = $data['pm_email_notify'];
		$insert_data['dateformat'] = get_date_format($data['time_format'], "%");
		$insert_data['timeformat'] = get_time_format($data['time_format'], "%");
		$insert_data['timezone'] = $data['time_offset'];
		$insert_data['timezone'] = str_replace(array('.0', '.00'), array('', ''), $insert_data['timezone']);
		$insert_data['buddylist'] = $data['buddy_list'];
		$insert_data['ignorelist'] = $data['pm_ignore_list'];
		$insert_data['regip'] = my_inet_pton($data['member_ip']);
		$insert_data['timeonline'] = $data['total_time_logged_in'];
		$insert_data['totalpms'] = $data['instant_messages'];
		$insert_data['unreadpms'] = $data['unread_messages'];
		$insert_data['signature'] = str_replace(array("[bgcolor=", "[/bgcolor]"), array("[color=", "[/color]"), preg_replace('#\[quote author\=(.*?) link\=topic\=([0-9]*).msg([0-9]*)\#msg([0-9]*) date\=(.*?)\]#i', "[quote='$1' pid='{$pid}' dateline='$5']", encode_to_utf8($data['signature'], "members", "users")));

		if($data['passwd'])
		{
			$insert_data['passwordconvert'] = $data['passwd'];
		}
		else if($data['password'])
		{
			$insert_data['passwordconvert'] = $data['password'];
		}

		$insert_data['passwordconverttype'] = 'smf2';

		return $insert_data;
	}

	function test()
	{
		$data = array(
			'ID_GROUP' => 1,
			'is_activated' => 1,
			'additionalGroups' => 2,
			'displaygroup' => 1,
			'id_member' => 3,
			'member_name' => 'Test?fdfs?? username',
			'email_address' => 'test@test.com',
			'dateRegistered' => 12345678,
			'lastLogin' => 23456789,
			'websiteUrl' => 'http://test.com',
			'avatar' => 'http://community.mybb.com/uploads/avatars/avatar_2165.png',
			'birthdate' => '27 April 1992',
			'ICQ' => '34567890',
			'AIM' => 'blarg',
			'YIM' => 'test@yahoo.com',
			'hideEmail' => 1,
			'showOnline' => 1,
			'pm_email_notify' => 1,
			'timeFormat' => 2,
			'timeOffset' => 10,
			'buddy_list' => '1,2,3',
			'pm_ignore_list' => '4,5,6',
			'memberIP' => '127.0.0.1',
			'totalTimeLoggedIn' => 1234567,
			'instantMessages' => 15,
			'unreadMessages' => 5,
			'signature' => 'Test, test, fdsfdsf ds dsf  est?fdf fdsfds s??',
			'password' => 'dsfdssw132rdstr13112rwedsxc',
		);

		$match_data = array(
			'usergroup' => 4,
			'additionalgroups' => 3,
			'displaygroup' => 4,
			'import_usergroup' => 1,
			'import_additionalgroups' => '2',
			'import_displaygroup' => 1,
			'import_uid' => 3,
			'username' => utf8_encode('Test?fdfs?? username'), // The Merge System should convert the mixed ASCII/Unicode string to proper UTF8
			'email' => 'test@test.com',
			'regdate' => 12345678,
			'lastactive' => 23456789,
			'lastvisit' => 23456789,
			'website' => 'http://test.com',
			'avatar' => 'http://community.mybb.com/uploads/avatars/avatar_2165.png',
			'avatardimensions' => '100|100',
			'avatartype' => 'remote',
			'birthday' => '4-27-1992',
			'icq' => '34567890',
			'aim' => 'blarg',
			'yahoo' => 'test@yahoo.com',
			'hideemail' => 1,
			'invisible' => 0,
			'pmnotify' => 1,
			'timeformat' => 2,
			'timezone' => 10,
			'buddylist' => '1,2,3',
			'ignorelist' => '4,5,6',
			'regip' => '127.0.0.1',
			'timeonline' => 1234567,
			'totalpms' => 15,
			'unreadpms' => 5,
			'signature' => utf8_encode('Test, test, fdsfdsf ds dsf  est?fdf fdsfds s??'),
			'passwordconvert' => 'dsfdssw132rdstr13112rwedsxc',
			'passwordconverttype' => 'smf11',
		);

		$this->assert($data, $match_data);
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
		$query = $this->old_db->simple_select("messages", "*", "id_member = '{$uid}'", array('order_by' => 'poster_time', 'order_dir' => 'ASC', 'limit' => 1));
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