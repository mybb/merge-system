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

class MINGLE_Converter_Module_Usergroups extends Converter_Module_Usergroups {

	var $settings = array(
		'friendly_name' => 'usergroups',
		'progress_column' => 'id',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session, $db;


		$query = $this->old_db->simple_select("forum_usergroups", "*", "1=1", array('limit_start' => $this->trackers['start_usergroups'], 'limit' => $import_session['usergroups_per_screen']));
		while($group = $this->old_db->fetch_array($query))
		{
			$gid = $this->insert($group);

			// Restore connections
			$users_in_group = "";
			$query2 = $this->old_db->simple_select("forum_usergroup2user", "user_id", "`group`='{$group['id']}'");
			while($user = $this->old_db->fetch_field($query2, 'user_id'))
			{
				if($users_in_group == "")
				{
					$users_in_group = $user;
				}
				else
				{
					$users_in_group .= ",".$user;
				}
			}

			if($users_in_group != "")
			{
				$db->update_query("users", array('usergroup' => $gid), "import_uid IN ({$users_in_group})");
			}
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// Mingle values
		$insert_data['import_gid'] = $data['id'];
		$insert_data['title'] = $data['name'];
		$insert_data['description'] = $data['description'];

		// TODO: group leaders?

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of usergroups
		if(!isset($import_session['total_usergroups']))
		{
			$query = $this->old_db->simple_select("forum_usergroups", "COUNT(*) as count", "1=1");
			$import_session['total_usergroups'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_usergroups'];
	}
}

?>
