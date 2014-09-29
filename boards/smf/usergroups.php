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

class SMF_Converter_Module_Usergroups extends Converter_Module_Usergroups {

	var $settings = array(
		'friendly_name' => 'usergroups',
		'progress_column' => 'ID_GROUP',
		'default_per_screen' => 1000,
	);

	var $permissions = array();

	function import()
	{
		global $import_session, $db;

		// Get only non-staff groups.
		$query = $this->old_db->simple_select("membergroups", "*", "ID_GROUP > 3 AND minPosts = -1", array('limit_start' => $this->trackers['start_usergroups'], 'limit' => $import_session['usergroups_per_screen']));
		while($group = $this->old_db->fetch_array($query))
		{
			$gid = $this->insert($group);

			// Update our internal cache array
			$this->get_import->cache_gids[$group['ID_GROUP']] = $gid;

			// Restore connections
			$db->update_query("users", array('usergroup' => $gid), "import_usergroup = '{$group['ID_GROUP']}' OR import_displaygroup = '{$group['ID_GROUP']}'");

			$query2 = $db->simple_select("users", "uid, import_additionalgroups AS additionalGroups", "CONCAT(',', import_additionalgroups, ',') LIKE '%,{$group['ID_GROUP']},%'");
			while($user = $db->fetch_array($query2))
			{
				$db->update_query("users", array('additionalgroups' => $this->board->get_group_id($user['additionalGroups'])), "uid = '{$user['uid']}'");
			}
			$db->free_result($query2);
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

		// SMF values
		$insert_data['import_gid'] = $data['ID_GROUP'];
		$insert_data['type'] = 2;
		$insert_data['title'] = $data['groupName'];
		$insert_data['description'] = 'SMF imported group';
		if(!empty($data['onlineColor']))
		{
			$insert_data['namestyle'] = "<span style=\"color: {$data['onlineColor']}\">{username}</span>";
		}
		else
		{
			$insert_data['namestyle'] = '{username}';
		}

		$star_info = explode('#', $data['stars']);
		$insert_data['stars'] = $star_info[0];
		$insert_data['starimage'] = 'images/'.$star_info[1];
		$insert_data['canviewprofiles'] = $this->permissions[$data['ID_GROUP']]['profile_view_any'];
		$insert_data['candlattachments'] = $this->permissions[$data['ID_GROUP']]['view_attachments'];
		$insert_data['canpostthreads'] = $this->permissions[$data['ID_GROUP']]['post_new'];
		$insert_data['canpostreplys'] = $this->permissions[$data['ID_GROUP']]['post_reply_any'];
		$insert_data['canpostattachments'] = $this->permissions[$data['ID_GROUP']]['post_attachment'];
		$insert_data['caneditposts'] = $this->permissions[$data['ID_GROUP']]['modify_own'];
		$insert_data['candeleteposts'] = $this->permissions[$data['ID_GROUP']]['remove_own'];
		$insert_data['candeletethreads'] = $this->permissions[$data['ID_GROUP']]['delete_own'];
		$insert_data['caneditattachments'] = $this->permissions[$data['ID_GROUP']]['post_attachment'];
		$insert_data['canpostpolls'] = $this->permissions[$data['ID_GROUP']]['poll_post'];
		$insert_data['canvotepolls'] = $this->permissions[$data['ID_GROUP']]['poll_vote'];
		$insert_data['canusepms'] = $this->permissions[$data['ID_GROUP']]['pm_read'];
		$insert_data['cansendpms'] = $this->permissions[$data['ID_GROUP']]['pm_send'];
		$insert_data['canviewmemberlist'] = $this->permissions[$data['ID_GROUP']]['view_mlist'];
		$insert_data['canviewcalendar'] = $this->permissions[$data['ID_GROUP']]['calendar_view'];
		$insert_data['canaddevents'] = $this->permissions[$data['ID_GROUP']]['calendar_post'];
		$insert_data['canviewonline'] = $this->permissions[$data['ID_GROUP']]['who_view'];
		$insert_data['cancp'] = $this->permissions[$data['ID_GROUP']]['admin_forum'];
		$insert_data['issupermod'] = $this->permissions[$data['ID_GROUP']]['moderate_board'];
		$insert_data['cansearch'] = $this->permissions[$data['ID_GROUP']]['search_posts'];
		$insert_data['canusercp'] = $this->permissions[$data['ID_GROUP']]['profile_identity_own'];
		$insert_data['usereputationsystem'] = $this->permissions[$data['ID_GROUP']]['karma_edit'];
		$insert_data['cangivereputations'] = $this->permissions[$data['ID_GROUP']]['karma_edit'];
		$insert_data['cancustomtitle'] = $this->permissions[$data['ID_GROUP']]['profile_title_own'];

		return $insert_data;
	}

	/**
	 * Get the usergroup permissions from SMF
	 *
	 * @return array group permissions
	 */
	function get_group_permissions()
	{
		$query = $this->old_db->simple_select("permissions", "*", "addDeny = 1");
		$permissions = array();
		while($permission = $this->old_db->fetch_array($query))
		{
			$permissions[$permission['ID_GROUP']][$permission['permission']] = 1;
		}
		$this->old_db->free_result($query);

		$query = $this->old_db->simple_select("board_permissions", "ID_GROUP, permission", "addDeny = 1 AND ID_BOARD = 0");
		while($permission = $this->old_db->fetch_array($query))
		{
			$permissions[$permission['ID_GROUP']][$permission['permission']] = 1;
		}
		$this->old_db->free_result($query);

		return $permissions;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of usergroups
		if(!isset($import_session['total_usergroups']))
		{
			$query = $this->old_db->simple_select("membergroups", "COUNT(*) as count", "ID_GROUP > 3 AND minPosts = -1");
			$import_session['total_usergroups'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_usergroups'];
	}
}

?>