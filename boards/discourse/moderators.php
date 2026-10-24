<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 *
 * Discourse Moderators Converter
 * Discourse admins and moderators are boolean flags on the user, not group
 * memberships. We import them by finding users with admin=true or moderator=true
 * and assigning them to MyBB admin/mod groups.
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class DISCOURSE_Converter_Module_Moderators extends Converter_Module_Moderators {

	var $settings = array(
		'friendly_name' => 'moderators',
		'progress_column' => 'id',
		'default_per_screen' => 100,
	);

	function import()
	{
		global $import_session;

		// Get admin users
		$query = $this->old_db->simple_select("users", "id, username", "admin = TRUE AND active = TRUE", array('limit_start' => $this->trackers['start_moderators'], 'limit' => $import_session['moderators_per_screen']));
		while($user = $this->old_db->fetch_array($query))
		{
			$insert_data = array(
				'uid' => $this->get_import->uid($user['id']),
				'import_uid' => $user['id'],
				'is_super_mod' => 1,
				'can_manage_reported_content' => 1,
				'can_manage_all_forums' => 1,
				'can_manage_announcements' => 1,
				'can_manage_mod_queue' => 1,
				'can_manage_posts' => 1,
				'can_edit_posts' => 1,
				'can_delete_posts' => 1,
				'can_view_ips' => 1,
				'can_ban_users' => 1,
				'can_edit_profiles' => 1,
			);
			$this->insert($insert_data);
		}

		// Get moderator users (non-admin moderators)
		$query = $this->old_db->simple_select("users", "id, username", "moderator = TRUE AND admin = FALSE AND active = TRUE", array('limit_start' => $this->trackers['start_moderators'], 'limit' => $import_session['moderators_per_screen']));
		while($user = $this->old_db->fetch_array($query))
		{
			$insert_data = array(
				'uid' => $this->get_import->uid($user['id']),
				'import_uid' => $user['id'],
				'is_super_mod' => 0,
				'can_manage_reported_content' => 1,
				'can_manage_all_forums' => 1,
				'can_manage_mod_queue' => 1,
				'can_manage_posts' => 1,
				'can_edit_posts' => 1,
				'can_delete_posts' => 1,
				'can_view_ips' => 1,
			);
			$this->insert($insert_data);
		}
	}

	function convert_data($data)
	{
		return $data;
	}

	function fetch_total()
	{
		global $import_session;

		if(!isset($import_session['total_moderators']))
		{
			$query = $this->old_db->simple_select("users", "COUNT(*) as count", "(admin = TRUE OR moderator = TRUE) AND active = TRUE");
			$import_session['total_moderators'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_moderators'];
	}
}
