<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 *
 * IPS5 User Converter - SKELETON
 * TODO: Verify all column names against a real IPS5 database
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class IPB5_Converter_Module_Users extends Converter_Module_Users {

	var $settings = array(
		'friendly_name' => "users",
		'progress_column' => "member_id", // TODO: verify column name
		'encode_table' => "core_members",  // TODO: verify table name
		'postnum_column' => "posts",       // TODO: verify column name (IPS5 may use 'member_posts', 'msg_count', etc.)
		'username_column' => 'name',       // TODO: verify column name
		'email_column' => 'email',         // TODO: verify column name
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;

		// Get members
		$query = $this->old_db->query("
			SELECT *
			FROM ".OLD_TABLE_PREFIX."core_members m
			LIMIT ".$this->trackers['start_users'].", ".$import_session['users_per_screen']
		);
		while($user = $this->old_db->fetch_array($query))
		{
			$this->insert($user);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// Invision Community 5 values
		// TODO: Verify ALL column names below against a real IPS5 database
		$insert_data['usergroup'] = $this->board->get_gid($data['member_group_id']);
		$insert_data['additionalgroups'] = $this->board->get_group_id($data['mgroup_others']);
		$insert_data['import_usergroup'] = $data['member_group_id'];
		$insert_data['import_additionalgroups'] = $data['mgroup_others'];
		$insert_data['import_uid'] = $data['member_id'];
		$insert_data['username'] = encode_to_utf8($data['name'], "core_members", "users");
		$insert_data['email'] = $data['email'];
		$insert_data['regdate'] = $data['joined'];
		$insert_data['lastactive'] = $data['last_activity'];
		$insert_data['lastvisit'] = $data['last_visit'];
		$insert_data['lastpost'] = $data['last_post'];
		$data['bday_day'] = trim($data['bday_day']);
		$data['bday_month'] = trim($data['bday_month']);
		$data['bday_year'] = trim($data['bday_year']);
		if(!empty($data['bday_day']) && !empty($data['bday_month']) && !empty($data['bday_year']))
		{
			$insert_data['birthday'] = $data['bday_day'].'-'.$data['bday_month'].'-'.$data['bday_year'];
		}
		if(!empty($data['timezone']))
		{
			$insert_data['timezone'] = get_timezone($data['timezone']);
		}
		$insert_data['regip'] = my_inet_pton($data['ip_address']);
		$insert_data['totalpms'] = $data['msg_count_total'];
		$insert_data['unreadpms'] = $data['msg_count_new'];
		$insert_data['signature'] =  $this->bbcode_parser->convert($data['signature']);

		// IPS5 uses Laravel's password hashing (bcrypt with $2y$ prefix or argon2)
		// TODO: Verify the password hash column name and format in IPS5
		// In IPS4: members_pass_hash + members_pass_salt
		// In IPS5 (Laravel): likely 'password' column containing $2y$ bcrypt or $argon2id$ hash
		$insert_data['passwordconvert'] = $data['members_pass_hash'];
		$insert_data['passwordconverttype'] = 'ipb5';

		// IPS5 may or may not store a separate salt (Laravel bcrypt embeds the salt in the hash)
		if(isset($data['members_pass_salt']))
		{
			$insert_data['passwordconvertsalt'] = $data['members_pass_salt'];
		}

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of members
		if(!isset($import_session['total_users']))
		{
			$query = $this->old_db->simple_select("core_members", "COUNT(*) as count");
			$import_session['total_users'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_users'];
	}
}
