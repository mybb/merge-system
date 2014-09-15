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

class XENFORO_Converter_Module_Users extends Converter_Module_Users {

	var $settings = array(
		'friendly_name' => 'users',
		'progress_column' => 'user_id',
		'encode_table' => 'user',
		'postnum_column' => 'posts', // TODO: Search it!
		'username_column' => 'username',
		'email_column' => 'email',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;
		
		// Get members
		$query = $this->old_db->query("SELECT *
				FROM ".OLD_TABLE_PREFIX."user u
				LEFT JOIN ".OLD_TABLE_PREFIX."user_profile p ON(p.user_id=u.user_id)
				LEFT JOIN ".OLD_TABLE_PREFIX."user_authenticate a ON(a.user_id=u.user_id)
				LIMIT {$this->trackers['start_users']}, {$import_session['users_per_screen']}");
		while($user = $this->old_db->fetch_array($query))
		{
			$this->insert($user);
		}
	}
	
	function convert_data($data)
	{
		$insert_data = array();
		
		// Xenforo 1 values
		$insert_data['usergroup'] = $this->board->get_group_id($data['user_group_id'], array("not_multiple" => true));
		$sec = explode(",", $data['secondary_group_ids']);
		$groups = array();
		foreach($sec as $gid)
		{
		    $groups[] = $this->board->get_group_id($gid);
		}
		$insert_data['additionalgroups'] = implode(",", $groups);
		$insert_data['displaygroup'] = $this->board->get_gid($data['display_style_group_id']);
		$insert_data['import_usergroup'] = $this->board->get_group_id($data['user_group_id'], array("original" => true));
		$insert_data['import_additionalgroups'] = $data['secondary_group_ids'];
		$insert_data['import_displaygroup'] = $data['display_style_group_id'];
		$insert_data['import_uid'] = $data['user_id'];
		$insert_data['username'] = encode_to_utf8($data['username'], "user", "users");
		$insert_data['email'] = $data['email'];
		$insert_data['regdate'] = $data['register_date'];
		$insert_data['lastactive'] = $data['last_activity'];
		$insert_data['lastvisit'] = $data['last_activity'];
		$insert_data['website'] = $data['homepage'];
		$insert_data['signature'] = encode_to_utf8($this->bbcode_parser->convert($data['signature']), "user", "users");
		if($data['dob_day'] != 0 && $data['dob_month'] != 0)
		{
			$insert_data['birthday'] = $data['dob_day']."-".$data['dob_month']."-".$data['dob_year'];
		}
		$insert_data['timezone'] = get_timezone($data['timezone']);

		if($data['scheme_class'] == "XenForo_Authentication_Core")
		{
			$insert_data['passwordconverttype'] = "xf";
		}
		else if($data['scheme_class'] == "XenForo_Authentication_Core12")
		{
			$insert_data['passwordconverttype'] = "xf12"; // Yeah, they changed their password hashing method in a minor release...
		}
		$password_data = unserialize($data['data']);
		$insert_data['passwordconvert'] = $password_data['hash'];

		return $insert_data;
	}
	
	function fetch_total()
	{
		global $import_session;
		
		// Get number of members
		if(!isset($import_session['total_users']))
		{
			$query = $this->old_db->simple_select("user", "COUNT(*) as count");
			$import_session['total_users'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}
		
		return $import_session['total_users'];
	}
}

?>