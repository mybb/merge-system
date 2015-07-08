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

define('FORUM_AVATAR_GIF', 1);
define('FORUM_AVATAR_JPG', 2);
define('FORUM_AVATAR_PNG', 3);

class PUNBB_Converter_Module_Avatars extends Converter_Module_Avatars {

	var $settings = array(
		'friendly_name' => 'users',
		'progress_column' => 'id',
		'default_per_screen' => 20,
	);

	function get_avatar_path()
	{
		$query = $this->old_db->simple_select('config', 'conf_value', "conf_name='o_avatars_dir'");
		$uploadspath = $this->old_db->fetch_field($query, 'conf_value');
		$this->old_db->free_result($query);

		return $uploadspath;
	}

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select("users", "*", "username != 'Guest' AND avatar > 0", array('limit_start' => $this->trackers['start_avatars'], 'limit' => $import_session['avatars_per_screen']));
		while($avatar = $this->old_db->fetch_array($query))
		{
			$this->insert($avatar);
		}
	}

	function convert_data($data)
	{
		global $insert_data;

		$insert_data = array();

		// MyBB 1.8 values
		$insert_data['uid'] = $this->get_import->uid($data['id']);

		$insert_data['avatar'] = $this->get_upload_avatar_name($insert_data['uid'], $this->generate_raw_filename($data));
		$insert_data['avatartype'] = AVATAR_TYPE_UPLOAD;
		$insert_data['avatardimensions'] = "{$data['avatar_height']}|{$data['avatar_width']}";

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of users with avatar
		if(!isset($import_session['total_avatars']))
		{
			$query = $this->old_db->simple_select("users", "COUNT(*) as count", "username != 'Guest' AND avatar > 0");
			$import_session['total_avatars'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_avatars'];
	}

	function generate_raw_filename($avatar)
	{
		switch($avatar['avatar']) {
			case FORUM_AVATAR_GIF:
				return $avatar['id'].".gif";
			case FORUM_AVATAR_JPG:
				return $avatar['id'].".jpg";
			case FORUM_AVATAR_PNG:
				return $avatar['id'].".png";
		}
		return '';
	}
}
