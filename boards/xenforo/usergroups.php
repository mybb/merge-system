<?php
/**
 * MyBB 1.6
 * Copyright © 2009 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
  * License: http://www.mybb.com/about/license
 *
 * $Id: usergroups.php 4394 2010-12-14 14:38:21Z ralgith $
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class XENFORO_Converter_Module_Usergroups extends Converter_Module_Usergroups {

	var $settings = array(
		'friendly_name' => 'usergroups',
		'progress_column' => 'usergroupid',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session, $db;
			
		// Get only non-staff groups.
		$query = $this->old_db->simple_select("user_group", "*", "user_group_id", array('limit_start' => $this->trackers['start_usergroups'], 'limit' => $import_session['usergroups_per_screen']));
		while($group = $this->old_db->fetch_array($query))
		{
			$gid = $this->insert($group);
			
			// Restore connections
			$db->update_query("users", array('user_group' => $gid), "import_usergroup = '2".intval($group['user_group_id'])."' OR import_displaygroup = '2".intval($group['user_group_id'])."'");
		}
	}
	
	function convert_data($data)
	{
		$insert_data = array();
		
		// Xenforo 1 values
		$insert_data['import_gid'] = $data['usergroupid'];
		$insert_data['title'] = $data['title'];
		$insert_data['description'] = $data['description'];
		$insert_data['pmquota'] = $data['pmquota'];
		$insert_data['maxpmrecipients'] = $data['pmsendmax'];
		$insert_data['attachquota'] = $data['attachlimit'];
		
		return $insert_data;
	}
	
	function fetch_total()
	{
		global $import_session;
		
		// Get number of usergroups
		if(!isset($import_session['total_usergroups']))
		{
			$query = $this->old_db->simple_select("user_group", "COUNT(*) as count", "user_group_id`='2");
			$import_session['total_usergroups'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}
		
		return $import_session['total_usergroups'];
	}
}

?>