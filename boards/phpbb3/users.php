<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 *
 * $Id: users.php 4397 2011-01-01 15:49:46Z ralgith $
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class PHPBB3_Converter_Module_Users extends Converter_Module_Users {

	var $settings = array(
		'friendly_name' => 'users',
		'progress_column' => 'user_id',
		'encode_table' => 'users',
		'postnum_column' => 'user_posts',
		'username_column' => 'username',
		'email_column' => 'user_email',
		'default_per_screen' => 1000,
	);

	var $get_private_messages_cache = array();

	function import()
	{
		global $import_session;

		// Get members
		$query = $this->old_db->simple_select("users", "*", "user_id > 0 AND username != 'Anonymous' AND group_id != 6", array('limit_start' => $this->trackers['start_users'], 'limit' => $import_session['users_per_screen']));
		while($user = $this->old_db->fetch_array($query))
		{
			$this->insert($user);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// phpBB 3 values
		$insert_data['usergroup'] = $this->board->get_group_id($data['user_id'], array("not_multiple" => true));
		$insert_data['additionalgroups'] = str_replace($insert_data['usergroup'], '', $this->board->get_group_id($data['user_id']));
		$insert_data['displaygroup'] = $this->board->get_group_id($data['user_id'], array("not_multiple" => true));

		//phpBB3 inactive for user registration (not yet activated) force awaiting and remove possible registered group from additionalgroups
		if($data['user_inactive_reason'] == '1' && $data['user_type'] == '1')
		{
			$insert_data['usergroup'] = 5;
			$insert_data['displaygroup'] = 5;
			$groups = array_flip(explode(',', $insert_data['additionalgroups']));
			unset($groups[2]);
			$insert_data['additionalgroups'] = implode(',', array_keys($groups));
		}

		$insert_data['import_usergroup'] = $this->board->get_group_id($data['user_id'], array("not_multiple" => true, "original" => true));
		$insert_data['import_additionalgroups'] = $this->board->get_group_id($data['user_id'], array("original" => true));
		$insert_data['import_displaygroup'] = $data['group_id'];
		$insert_data['import_uid'] = $data['user_id'];
		$insert_data['username'] = encode_to_utf8($data['username'], "users", "users");
		$insert_data['email'] = $data['user_email'];
		$insert_data['regdate'] = $data['user_regdate'];
		$insert_data['lastactive'] = $data['user_lastvisit'];
		$insert_data['lastvisit'] = $data['user_lastvisit'];
		$insert_data['website'] = $data['user_website'];
		$insert_data['avatardimensions'] = $data['user_avatar_width'].'|'.$data['user_avatar_height'];
		if($insert_data['avatardimensions'] == '0x0')
		{
			$insert_data['avatardimensions'] = '';
		}
		$insert_data['avatar'] = $data['avatar'];
		$insert_data['lastpost'] = $data['user_lastpost_time'];

		$birthday = '';
		$data['user_birthday'] = trim($data['user_birthday']);
		if(!empty($data['user_birthday']))
		{
			$birthday_arr = explode('-', $data['user_birthday']);

			foreach($birthday_arr as $bday_part)
			{
				if(substr($bday_part, 0, 1) == "0")
				{
					$birthday .= substr($bday_part, 1);
				}
				else
				{
					$birthday .= $bday_part;
				}

				$birthday .= "-";
			}
		}

		$insert_data['birthday'] = $birthday;
		$insert_data['icq'] = $data['user_icq'];
		$insert_data['aim'] = $data['user_aim'];
		$insert_data['yahoo'] = $data['user_yim'];
		$insert_data['msn'] = $data['user_msnm'];
		$insert_data['hideemail'] = $data['user_allow_viewemail'];
		$insert_data['invisible'] = $data['user_allow_viewonline'];
		$insert_data['allownotices'] = $data['user_notify'];
		if($data['user_notify'] == 1)
		{
			$insert_data['subscriptionmethod'] = 2;
		}
		else
		{
			$insert_data['subscriptionmethod'] = 0;
		}
		$insert_data['receivepms'] = $data['user_allow_pm'];
		$insert_data['pmnotice'] = $data['user_notify_pm'];
		$insert_data['pmnotify'] = $data['user_notify_pm'];
		$date = $data['user_dateformat'];
		if(strpos($date, 'd M Y') !== FALSE)
		{
			$dateformat = 11;
		}
		elseif(strpos($date, 'D M d') !== FALSE)
		{
			$dateformat = 10;
		}
		elseif (strpos($date, 'jS') !== FALSE)
		{
			$dateformat = 9;
		}
		else
		{
			$dateformat = 10;
		}

		if(strpos($date, 'H:i') !== FALSE)
		{
			$timeformat = 3;
		}
		elseif (strpos($date, 'g:i') !== FALSE)
		{
			$timeformat = 1;
		}
		else
		{
			$timeformat = 1;
		}
		$insert_data['dateformat'] = $dateformat;
		$insert_data['timeformat'] = $timeformat;
		$insert_data['timezone'] = $data['user_timezone'];
		$insert_data['timezone'] = str_replace(array('.0', '.00'), array('', ''), $insert_data['timezone']);
		$insert_data['dst'] = $data['user_dst'];
		$insert_data['signature'] = encode_to_utf8($this->bbcode_parser->convert($data['user_sig'], $data['user_sig_bbcode_uid']), "users", "users");
		$insert_data['regip'] = my_inet_pton($data['user_ip']);
		$insert_data['lastip'] = my_inet_pton($data['user_ip']);
		$insert_data['totalpms'] = $this->get_private_messages($data['user_id']);
		$insert_data['unreadpms'] = $data['user_unread_privmsg'];
		$insert_data['salt'] = $data['user_form_salt'];
		$insert_data['passwordconvert'] = $data['user_password'];
		$insert_data['passwordconverttype'] = 'phpbb3';
		$insert_data['loginkey'] = generate_loginkey();

		return $insert_data;
	}

	function test()
	{
		$this->get_private_messages_cache = array(
			1 => 150,
		);

		$data = array(
			'user_id' => 1,
			'usergroup' => 4,
			'group_id' => 2,
			'username' => 'Testéfdfsÿÿ username',
			'user_email' => 'test@test.com',
			'user_regdate' => 12345678,
			'user_lastvisit' => 23456789,
			'user_website' => 'http://test.com',
			'user_avatar_width' => 100,
			'user_avatar_height' => 100,
			'avatar' => 'http://community.mybb.com/uploads/avatars/avatar_2165.png',
			'user_lastpost_time' => 12345689,
			'user_birthday' => '4-27-1992',
			'user_icq' => '34567890',
			'user_aim' => 'blarg',
			'user_yim' => 'test@yahoo.com',
			'user_msnm' => 'test@hotmail.com',
			'user_allow_viewemail' => 1,
			'user_allow_viewonline' => 0,
			'user_notify' => 1,
			'user_allow_pm' => 1,
			'user_notify_pm' => 1,
			'user_dateformat' => 2,
			'user_timezone' => 10.0,
			'user_dst' => 1,
			'user_sig' => 'Test, test, fdsfdsf ds dsf  estéfdf fdsfds sÿÿ',
			'user_sig_bbcode_uid' => 1,
			'user_ip' => '127.0.0.1',
			'user_unread_privmsg' => 5,
			'user_form_salt' => '5XfjI',
			'user_password' => 'dsfdssw132rdstr13112rwedsxc',
		);

		$match_data = array(
			'usergroup' => 1,
			'additionalgroups' => '',
			'displaygroup' => 1,
			'import_usergroup' => 1,
			'import_additionalgroups' => '',
			'import_displaygroup' => 2,
			'import_uid' => 1,
			'username' => utf8_encode('Testéfdfsÿÿ username'), // The Merge System should convert the mixed ASCII/Unicode string to proper UTF8
			'email' => 'test@test.com',
			'regdate' => 12345678,
			'lastactive' => 23456789,
			'lastvisit' => 23456789,
			'website' => 'http://test.com',
			'avatardimensions' => '100|100',
			'avatar' => 'http://community.mybb.com/uploads/avatars/avatar_2165.png',
			'lastpost' => 12345689,
			'birthday' => '4-27-1992',
			'icq' => '34567890',
			'aim' => 'blarg',
			'yahoo' => 'test@yahoo.com',
			'msn' => 'test@hotmail.com',
			'hideemail' => 1,
			'invisible' => 0,
			'allownotices' => 1,
			'receivepms' => 1,
			'pmnotice' => 1,
			'pmnotify' => 1,
			'timeformat' => 2,
			'timezone' => 10,
			'dst' => 1,
			'signature' => utf8_encode('Test, test, fdsfdsf ds dsf  estéfdf fdsfds sÿÿ'),
			'regip' => '127.0.0.1',
			'totalpms' => 150,
			'unreadpms' => 5,
			'salt' => '5XfjI',
			'passwordconvert' => 'dsfdssw132rdstr13112rwedsxc',
			'passwordconverttype' => 'phpbb3',
		);

		$this->assert($data, $match_data);
	}

	/**
	 * Get total number of Private Messages the user has from the phpBB database
	 *
	 * @param int User ID
	 * @return int Number of Private Messages
	 */
	function get_private_messages($uid)
	{
		if(array_key_exists($uid, $this->get_private_messages_cache))
		{
			return $this->get_private_messages_cache[$uid];
		}

		$query = $this->old_db->simple_select("privmsgs", "COUNT(*) as pms", "to_address = '{$uid}' OR author_id = '{$uid}'");

		$results = $this->old_db->fetch_field($query, 'pms');
		$this->old_db->free_result($query);

		$this->get_private_messages_cache[$uid] = $results;

		return $results;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of members
		if(!isset($import_session['total_users']))
		{
			$query = $this->old_db->simple_select("users", "COUNT(*) as count", "user_id > 0 AND username != 'Anonymous' AND group_id != 6");
			$import_session['total_users'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_users'];
	}
}

?>