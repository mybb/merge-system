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
		$insert_data['hideemail'] = $data['user_allow_viewemail'];
		$insert_data['invisible'] = int_to_01($data['user_allow_viewonline']);
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

		$insert_data['dateformat'] = get_date_format($data['user_dateformat']);
		$insert_data['timeformat'] = get_time_format($data['user_dateformat']);
		$insert_data['timezone'] = $data['user_timezone'];
		$insert_data['timezone'] = str_replace(array('.0', '.00'), array('', ''), $insert_data['timezone']);
		$insert_data['dst'] = $data['user_dst'];
		$insert_data['signature'] = encode_to_utf8($this->bbcode_parser->convert($data['user_sig'], $data['user_sig_bbcode_uid']), "users", "users");
		$insert_data['regip'] = my_inet_pton($data['user_ip']);
		$insert_data['lastip'] = my_inet_pton($data['user_ip']);
		$insert_data['totalpms'] = $this->get_private_messages($data['user_id']);
		$insert_data['unreadpms'] = $data['user_unread_privmsg'];
		$insert_data['passwordconvert'] = $data['user_password'];
		$insert_data['passwordconverttype'] = 'phpbb3';
		$insert_data['loginkey'] = generate_loginkey();

		return $insert_data;
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