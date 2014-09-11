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

class WBB4_Converter_Module_Users extends Converter_Module_Users {

	var $settings = array(
		'friendly_name' => 'users',
		'progress_column' => 'userID',
		'encode_table' => 'user',
		'postnum_column' => 'wbbPosts',
		'username_column' => 'username',
		'email_column' => 'email',
		'default_per_screen' => 1000,
	);

	var $options = array(
		"homepage",
		"birthday",
		"timezone",
		"icq",
		"skype",
		"googlePlus",
	);

	var $nice_options;
	var $fields;

	function pre_setup()
	{
		global $import_session;
		
		// WBB saves the options in a table with columns named "userOption{ID}" and in a seperate table they have the nice name with the ID
		if(!isset($import_session['nice_options']))
		{
			$query = $this->old_db->simple_select(WCF_PREFIX."user_option", "optionID, optionName", "optionName IN ('".implode("','", $this->options)."')");
			while($option = $this->old_db->fetch_array($query))
			{
				$this->nice_options[$option['optionID']] = $option['optionName'];
			}
			$this->old_db->free_result($query);

			$import_session['nice_options'] = $this->nice_options;
		}
		else
		{
			$this->nice_options = $import_session['nice_options'];
		}
		$this->fields = "o.userOption".implode(", o.userOption", array_keys($this->nice_options));
	}

	function finish()
	{
		global $import_session;
		
		unset($import_session['nice_options']);
	}

	function import()
	{
		global $import_session;

		// We need to do that as WBB uses different prefixes and we cant set it above
		$this->settings['encode_table'] = WCF_PREFIX.$this->settings['encode_table'];

		// Get members
		$query = $this->old_db->query("SELECT u.*, {$this->fields}
			FROM ".WCF_PREFIX."user u
			LEFT JOIN ".WCF_PREFIX."user_option_value o ON (o.userID=u.userID)
			LIMIT {$this->trackers['start_users']}, {$import_session['users_per_screen']}");

    	while($user = $this->old_db->fetch_array($query))
		{
			// Use nice names at least for the ones we use
			foreach($this->nice_options as $id => $name)
			{
				$user[$name] = $user['userOption'.$id];
				unset($user['userOption'.$id]);
			}

			$this->insert($user);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// WBB 4 values
		$insert_data['usergroup'] = $this->board->get_group_id($data['userID'], array("not_multiple" => true));
		$insert_data['additionalgroups'] = $this->board->get_group_id($data['userID']);
		$insert_data['displaygroup'] = $this->board->get_gid($data['userOnlineGroupID']);

		$insert_data['import_usergroup'] = $this->board->get_group_id($data['userID'], array("not_multiple" => true, "original" => true));
		$insert_data['import_additionalgroups'] = $this->board->get_group_id($data['userID'], array("original" => true));
		$insert_data['import_displaygroup'] = $data['userOnlineGroupID'];

		$insert_data['import_uid'] = $data['userID'];
		$insert_data['username'] = encode_to_utf8($data['username'], WCF_PREFIX."user", "users");
		$insert_data['email'] = $data['email'];
		$insert_data['usertitle'] = $data['userTitle'];
		$insert_data['regdate'] = $data['registrationDate'];
		$insert_data['lastactive'] = $data['lastActivityTime'];
		$insert_data['lastvisit'] = $data['lastActivityTime'];
		if($data['homepage'] != "http://")
		{
			$insert_data['website'] = $data['homepage'];
		}

		$birthday = '';
		if(!empty($data['birthday']) && $data['birthday'] != "0000-00-00")
		{
			$birthday_arr = array_reverse(explode('-', $data['birthday']));

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
		$insert_data['icq'] = $data['icq'];
		$insert_data['skype'] = $data['skype'];
		$insert_data['google'] = $data['googlePlus'];

		if(!empty($data['timezone']))
		{
			$insert_data['timezone'] = get_timezone($data['timezone']);
		}
		$insert_data['signature'] = encode_to_utf8($this->bbcode_parser->convert($data['signature']), WCF_PREFIX."user", "users");
		$insert_data['regip'] = my_inet_pton($data['registrationIpAddress']);

		$insert_data['passwordconvert'] = $data['password'];
		$insert_data['passwordconverttype'] = 'wbb4';
		$insert_data['loginkey'] = generate_loginkey();

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of members
		if(!isset($import_session['total_users']))
		{
			// No need for the LEFT JOINS, simply query the user table
			$query = $this->old_db->simple_select(WCF_PREFIX."user", "COUNT(*) as count");
			$import_session['total_users'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_users'];
	}
}

?>