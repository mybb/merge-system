<?php
/**
 * MyBB 1.6
 * Copyright 2009 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
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
		$last_post = $this->get_last_post($data['ID_MEMBER']);
		$insert_data['lastpost'] = $last_post['posterTime'];
		$data['birthdate'] = trim($data['birthdate']);
		if(!empty($data['birthdate']))
		{
			$insert_data['birthday'] = date("n-j-Y", strtotime($data['birthdate']));
		}
		$insert_data['icq'] = $data['ICQ'];
		$insert_data['aim'] = $data['AIM'];
		$insert_data['yahoo'] = $data['YIM'];
		$insert_data['msn'] = $data['MSN'];
		$insert_data['hideemail'] = $data['hideEmail'];
		$insert_data['invisible'] = int_to_01($data['showOnline']);
		$insert_data['pmnotify'] = $data['pm_email_notify'];
		$insert_data['timeformat'] = $data['timeFormat'];
		$insert_data['timezone'] = $data['timeOffset'];
		$insert_data['timezone'] = str_replace(array('.0', '.00'), array('', ''), $insert_data['timezone']);
		$insert_data['buddylist'] = $data['buddy_list'];
		$insert_data['ignorelist'] = $data['pm_ignore_list'];
		$insert_data['regip'] = $data['memberIP'];
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

	function test()
	{
		$data = array(
			'ID_GROUP' => 1,
			'is_activated' => 1,
			'additionalGroups' => 2,
			'displaygroup' => 1,
			'ID_MEMBER' => 3,
			'memberName' => 'Test�fdfs�� username',
			'emailAddress' => 'test@test.com',
			'dateRegistered' => 12345678,
			'lastLogin' => 23456789,
			'websiteUrl' => 'http://test.com',
			'avatar' => 'http://community.mybb.com/uploads/avatars/avatar_2165.png',
			'birthdate' => '27 April 1992',
			'ICQ' => '34567890',
			'AIM' => 'blarg',
			'YIM' => 'test@yahoo.com',
			'MSN' => 'test@hotmail.com',
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
			'signature' => 'Test, test, fdsfdsf ds dsf  est�fdf fdsfds s��',
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
			'username' => utf8_encode('Test�fdfs�� username'), // The Merge System should convert the mixed ASCII/Unicode string to proper UTF8
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
			'msn' => 'test@hotmail.com',
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
			'signature' => utf8_encode('Test, test, fdsfdsf ds dsf  est�fdf fdsfds s��'),
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