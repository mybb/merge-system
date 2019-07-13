<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2019 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class DZX25_Converter_Module_Usergroups extends Converter_Module_Usergroups {
	
	var $settings = array(
			'friendly_name' => 'usergroups',
			'progress_column' => 'groupid',
			'default_per_screen' => 1000,
	);
	
	var $permissions = array();
	
	function import()
	{
		global $import_session;
		
		// Get only non-staff groups.
		$query = $this->old_db->simple_select("common_usergroup", "*", "type != 'system'", array('limit_start' => $this->trackers['start_usergroups'], 'limit' => $import_session['usergroups_per_screen']));
		while($group = $this->old_db->fetch_array($query))
		{
			$this->insert($group);
		}
	}
	
	function convert_data($data)
	{
		if(!$this->permissions)
		{
			// Cache permissions
			$this->permissions = $this->get_group_permissions();
		}
		
		$insert_data = array();
		
		// Discuz! X2.5 values
		$dxz_groupid = $data['groupid'];
		
		// From table `common_usergroup`:
		$insert_data['import_gid'] = $dxz_groupid;
		$insert_data['type'] = 2;
		$insert_data['title'] = $this->board->encode_to_utf8($data['grouptitle'], "common_usergroup", "usergroups");
		$insert_data['description'] = 'Discuz! X2.5 imported group';
		if(!empty($data['color']))
		{
			$insert_data['namestyle'] = "<span style=\"color: #{$data['color']};\">{username}</span>";
		}
		else
		{
			$insert_data['namestyle'] = '{username}';
		}
		
		$insert_data['stars'] = $data['stars'];
		$insert_data['canview'] = $data['allowvisit'] > 0 ? 1 : 0;
		$insert_data['canviewboardclosed'] = $data['allowvisit'] == 2 ? 1 : 0;
		$insert_data['cansendpms'] = $data['allowsendpm'];
		
		// From table `common_setting`:
		$insert_data['canviewmemberlist'] = $this->permissions[$dxz_groupid]['memliststatus'];
		
		// From table `common_usergroup_field`:
		$insert_data['canviewthreads'] = $this->permissions[$dxz_groupid]['readaccess'] > 0 ? 1 : 0;
		$insert_data['candlattachments'] = $this->permissions[$dxz_groupid]['allowgetattach'] || $this->permissions[$dxz_groupid]['allowgetimage'] ? 1 : 0;
		$insert_data['canpostthreads'] = $this->permissions[$dxz_groupid]['allowpost'];
		$insert_data['canpostreplys'] = $this->permissions[$dxz_groupid]['allowreply'];
		$insert_data['canpostattachments'] = $this->permissions[$dxz_groupid]['allowpostattach'] || $this->permissions[$dxz_groupid]['allowpostimage'] ? 1 : 0;
		// This field in Discuz! interacts with forum permissions.
		$insert_data['modposts'] = $this->permissions[$dxz_groupid]['allowdirectpost'] > 0 ? 0 : 1;
		// This field in Discuz! interacts with forum permissions.
		$insert_data['modthreads'] = $this->permissions[$dxz_groupid]['allowdirectpost'] > 0 ? 0 : 1;
		$insert_data['mod_edit_posts'] = $this->permissions[$dxz_groupid]['allowdirectpost'] == 0 ? 1 : 0;
		$insert_data['canpostpolls'] = $this->permissions[$dxz_groupid]['allowpostpoll'];
		$insert_data['canvotepolls'] = $this->permissions[$dxz_groupid]['allowvote'];
		$insert_data['cansearch'] = $this->permissions[$dxz_groupid]['allowsearch'] & 16;
		$insert_data['cancustomtitle'] = $this->permissions[$dxz_groupid]['allowcstatus'];
		$insert_data['canusesig'] = $this->permissions[$dxz_groupid]['maxsigsize'] > 0 ? 1 : 0;
		$insert_data['edittimelimit'] = $this->permissions[$dxz_groupid]['edittimelimit'];
		
		// From table `common_admingroup`:
		if($data['radminid'] != 0 && $dxz_groupid == $this->permissions[$dxz_groupid]['admingid'])
		{
			$insert_data['canviewonlineips'] = $this->permissions[$dxz_groupid]['allowviewip'];
			$insert_data['issupermod'] = $data['radminid'] == 2 ? 1 : 0;
			$insert_data['canmodcp'] =  1;
			$insert_data['canmanageannounce'] = $this->permissions[$dxz_groupid]['allowpostannounce'];
			$insert_data['canmanagemodqueue'] = $this->permissions[$dxz_groupid]['allowmodpost'] || $this->permissions[$dxz_groupid]['allowmassprune'] ? 1 : 0;
			$insert_data['canviewmodlogs'] = $this->permissions[$dxz_groupid]['allowviewlog'];
			$insert_data['caneditprofiles'] = $this->permissions[$dxz_groupid]['allowedituser'];
			$insert_data['canbanusers'] = $this->permissions[$dxz_groupid]['allowbanuser'] || $this->permissions[$dxz_groupid]['allowbanvisituser'] ? 1 : 0;
		}
		
		return $insert_data;
	}
	
	/**
	 * Get the usergroup permissions from Discuz! X2.5
	 *
	 * @return array group permissions
	 */
	function get_group_permissions()
	{
		$common_group_permissions = array();
		// Can a member view member list? MyBB has this setting defined for separated groups.
		$query = $this->old_db->simple_select("common_setting", "skey, svalue", "skey = 'memliststatus' limit 1");
		$result = $this->old_db->fetch_field($query, 'skey');
		$common_group_permissions['canviewmemberlist'] = int_to_yes_no($result, 1);
		$this->old_db->free_result($query);
		
		// $GROUP_ID => array( '$PERMISSION_NAME' => '$PERMISSION_VALUE' )
		$group_permissions = array();
		$group_permission_fields = array();
		
		// Get group ids.
		$query = $this->old_db->simple_select("common_usergroup", "groupid", "type != 'system'");
		while($groupid = $this->old_db->fetch_array($query))
		{
			$group_permissions[$groupid['groupid']] = array();
			foreach($common_group_permissions as $key => $value)
			{
				$group_permissions[$groupid['groupid']][$key] = $value;
			}
		}
		$this->old_db->free_result($query);
		
		// Permissions in `common_usergroup` table is converted in this module!
		
		// Get permissions in `common_usergroup_field` table.
		unset($group_permission_fields);
		$query = $this->old_db->simple_select("common_usergroup_field", "*", "groupid IN('".implode("','", array_keys($group_permissions))."')");
		while($permission = $this->old_db->fetch_array($query))
		{
			if(!isset($group_permission_fields))
			{
				//$group_permission_fields = array_slice(array_keys($permission), 1);
				$group_permission_fields = array_keys($permission);
			}
			
			foreach($permission as $key => $value)
			{
				if($key == 'groupid')
				{
					continue;
				}
				$group_permissions[$permission['groupid']][$key] = $value;
			}
		}
		$this->old_db->free_result($query);		
		
		// Permissions in `common_admingroup` table is converted in this module!
		unset($group_permission_fields);
		$query = $this->old_db->simple_select("common_admingroup", "*", "admingid IN('".implode("','", array_keys($group_permissions))."')");
		while($permission = $this->old_db->fetch_array($query))
		{
			if(!isset($group_permission_fields))
			{
				//$group_permission_fields = array_slice(array_keys($permission), 1);
				$group_permission_fields = array_keys($permission);
			}
			
			foreach($permission as $key => $value)
			{
				$group_permissions[$permission['admingid']][$key] = $value;
			}
		}
		$this->old_db->free_result($query);
		
		return $group_permissions;
	}
	
	function fetch_total()
	{
		global $import_session;
		
		// Get number of usergroups
		if(!isset($import_session['total_usergroups']))
		{
			$query = $this->old_db->simple_select("common_usergroup", "COUNT(*) as count", "type != 'system'");
			$import_session['total_usergroups'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}
		
		return $import_session['total_usergroups'];
	}
}


