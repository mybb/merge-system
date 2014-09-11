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

class VANILLA_Converter_Module_Usergroups extends Converter_Module_Usergroups {

	var $settings = array(
		'friendly_name' => 'usergroups',
		'progress_column' => 'RoleID',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session, $db;

		// Get only non-standard groups.
		$query = $this->old_db->simple_select("role", "*", "RoleID NOT IN ('".implode("','", array_keys($this->board->groups))."')", array('limit_start' => $this->trackers['start_usergroups'], 'limit' => $import_session['usergroups_per_screen']));
		while($group = $this->old_db->fetch_array($query))
		{
			$gid = $this->insert($group);

			// Restore connections
			$db->update_query("users", array('usergroup' => $gid), "import_usergroup = '".intval($group['RoleID'])."' OR import_displaygroup = '".intval($group['RoleID'])."'");
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// Vanilla values
		$insert_data['import_gid'] = $data['Role'];
		$insert_data['title'] = $data['Name'];
		$insert_data['description'] = $data['Description'];

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of usergroups
		if(!isset($import_session['total_usergroups']))
		{
			$query = $this->old_db->simple_select("role", "COUNT(*) as count", "RoleID NOT IN ('".implode("','", array_keys($this->board->groups))."')");
			$import_session['total_usergroups'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_usergroups'];
	}
}

?>