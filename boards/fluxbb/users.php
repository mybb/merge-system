<?php
/**
 * MyBB 1.6
 * Copyright © 2009 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
  * License: http://www.mybb.com/about/license
 *
 * $Id: users.php 4394 2010-12-14 14:38:21Z ralgith $
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class FLUXBB_Converter_Module_Users extends Converter_Module_Users {

	var $settings = array(
		'friendly_name' => 'users',
		'progress_column' => 'id',
		'encode_table' => 'users',
		'postnum_column' => 'num_posts',
		'username_column' => 'username',
		'email_column' => 'email',
		'default_per_screen' => 1000,
	);
	
	function pre_setup()
	{
		global $import_session;
		
		if(!isset($import_session['avatar_directory']))
		{
			$query = $this->old_db->simple_select("config", "conf_value, conf_name", "conf_name = 'o_avatars_dir' OR conf_name = 'o_base_url'");
			if($this->old_db->fetch_field($query, 'conf_name') == 'o_avatar_dir')
			{
				$import_session['avatar_directory'] = $this->old_db->fetch_field($query, 'conf_value');
			}
			else
			{
				$import_session['main_directory'] = $this->old_db->fetch_field($query, 'conf_value');
			}
			$this->old_db->free_result($query);
		}
	}

	function import()
	{
		global $import_session;
		
		// Get members
		$query = $this->old_db->simple_select("users", "*", "username != 'Guest'", array('order_by' => 'id', 'order_dir' => 'asc', 'limit_start' => $this->trackers['start_users'], 'limit' => $import_session['users_per_screen']));
		while($user = $this->old_db->fetch_array($query))
		{
			$this->insert($user);
		}
	}
	
	function convert_data($data)
	{
		$insert_data = array();
		
		// fluxBB values
		$insert_data['usergroup'] = $this->board->get_group_id($data['group_id'], array("not_multiple" => true));
		$insert_data['displaygroup'] = $this->board->get_group_id($data['group_id'], array("not_multiple" => true));
		$insert_data['import_usergroup'] = $this->board->get_group_id($data['group_id'], array("not_multiple" => true, "original" => true));
		$insert_data['import_additionalgroups'] = $this->board->get_group_id($data['group_id'], array("original" => true));
		$insert_data['import_displaygroup'] = $data['group_id'];
		$insert_data['import_uid'] = $data['id'];
		$insert_data['username'] = encode_to_utf8($data['username'], "users", "users");
		$insert_data['email'] = $data['email'];
		$insert_data['regdate'] = $data['registered'];
		$insert_data['lastactive'] = $data['last_visit'];
		$insert_data['lastvisit'] = $data['last_visit'];
		$insert_data['website'] = $data['url'];
		$insert_data['showsigs'] = $data['show_sig'];
		$insert_data['signature'] = encode_to_utf8($data['signature'], "users", "users");
		$insert_data['showavatars'] = $data['show_avatars'];
		$insert_data['timezone'] = str_replace(array('.0', '.00'), array('', ''), $data['timezone']);

		$insert_data['lastpost'] = (int)$data['last_post'];				
		$insert_data['icq'] = $data['icq'];
		$insert_data['aim'] = $data['aim'];
		$insert_data['yahoo'] = $data['yahoo'];
		$insert_data['hideemail'] = $data['email_setting'];
		$insert_data['allownotices'] = $data['notify_with_post'];
		$insert_data['regip'] = my_inet_pton($data['registration_ip']);
		$insert_data['passwordconvertsalt'] = $data['salt'];
		$insert_data['passwordconvert'] = $data['password'];
		$insert_data['passwordconverttype'] = 'fluxbb';
		
		return $insert_data;
	}
	
	function fetch_total()
	{
		global $import_session;
		
		// Get number of members
		if(!isset($import_session['total_users']))
		{
			$query = $this->old_db->simple_select("users", "COUNT(*) as count", "username != 'Guest'");
			$import_session['total_users'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}
		
		return $import_session['total_users'];
	}
}

?>