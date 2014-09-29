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

class MYBB_Converter_Module_Users extends Converter_Module_Users {

	var $settings = array(
		'friendly_name' => 'users',
		'progress_column' => 'uid',
		'encode_table' => 'users',
		'postnum_column' => 'postnum',
		'username_column' => 'username',
		'email_column' => 'email',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;

		// Get members
		$query = $this->old_db->simple_select("users", "*", "", array('order_by' => 'uid', 'order_dir' => 'asc', 'limit_start' => $this->trackers['start_users'], 'limit' => $import_session['users_per_screen']));
		while($user = $this->old_db->fetch_array($query))
		{
			$this->insert($user);
		}

		if($this->old_db->num_rows($query) == 0)
		{
			$output->print_none_left_message();
		}
	}

	function convert_data($data)
	{
		global $db;
		static $field_info;

		if(!isset($field_info))
		{
			// Get columns so we avoid any 'unknown column' errors
			$field_info = $db->show_fields_from("users");
		}

		$insert_data = array();

		foreach($field_info as $key => $field)
		{
			if($field['Extra'] == 'auto_increment')
			{
				if($db->type != "sqlite")
				{
					unset($insert_data[$field['Field']]);
				}
				continue;
			}

			if(isset($data[$field['Field']]))
			{
				$insert_data[$field['Field']] = $data[$field['Field']];
			}
		}

		// MyBB 1.8 values
		$insert_data['import_uid'] = $data['uid'];
		$insert_data['usergroup'] = $this->board->get_group_id($data['usergroup'], array("not_multiple" => true));
		$insert_data['additionalgroups'] = str_replace($insert_data['usergroup'], '', $this->board->get_group_id($data['usergroup']));
		$insert_data['username'] = encode_to_utf8($insert_data['username'], "users", "users");
		$insert_data['signature'] = encode_to_utf8($data['signature'], "users", "users");
		$insert_data['displaygroup'] = $this->board->get_group_id($data['displaygroup'], array("not_multiple" => true));
		$insert_data['import_usergroup'] = $data['usergroup'];
		$insert_data['import_additionalgroups'] = $data['additionalgroups'];

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of members
		if(!isset($import_session['total_users']))
		{
			$query = $this->old_db->simple_select("users", "COUNT(*) as count");
			$import_session['total_users'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_users'];
	}
}

?>