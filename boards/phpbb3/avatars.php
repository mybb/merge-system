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

class PHPBB3_Converter_Module_Avatars extends Converter_Module_Avatars {

	var $settings = array(
		'friendly_name' => 'avatars',
		'progress_column' => 'user_id',
		'default_per_screen' => 20,
	);

	var $avatar_salt;

	function get_avatar_path()
	{
		$query = $this->old_db->simple_select("config", "config_value", "config_name = 'server_protocol'", array('limit' => 1));
		$uploadspath = $this->old_db->fetch_field($query, 'config_value');
		$this->old_db->free_result($query);

		$query = $this->old_db->simple_select("config", "config_value", "config_name = 'server_name'", array('limit' => 1));
		$uploadspath .= $this->old_db->fetch_field($query, 'config_value');
		$this->old_db->free_result($query);

		$query = $this->old_db->simple_select("config", "config_value", "config_name = 'script_path'", array('limit' => 1));
		$uploadspath .= $this->old_db->fetch_field($query, 'config_value').'/';
		$this->old_db->free_result($query);

		$query = $this->old_db->simple_select("config", "config_value", "config_name = 'avatar_path'", array('limit' => 1));
		$uploadspath .= $this->old_db->fetch_field($query, 'config_value');
		$this->old_db->free_result($query);

		return $uploadspath;
	}

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select("users", "*", "user_id > 0 AND username != 'Anonymous' AND group_id != 6", array('limit_start' => $this->trackers['start_avatars'], 'limit' => $import_session['avatars_per_screen']));
		while($avatar = $this->old_db->fetch_array($query))
		{
			$this->insert($avatar);
		}
	}

	function convert_data($data)
	{
		global $mybb;

		$insert_data = array();

		// MyBB 1.8 values
		$insert_data['uid'] = $this->get_import->uid($data['user_id']);

		if($data['user_avatar_type'] == 1 || $data['user_avatar_type'] == 'avatar.driver.upload')
		{
			$insert_data['avatartype'] = AVATAR_TYPE_UPLOAD;
			$insert_data['avatar'] = $this->get_upload_avatar_name($insert_data['uid'], $data['user_avatar']);
			$insert_data['avatardimensions'] = "{$data['user_avatar_height']}|{$data['user_avatar_width']}";
		}
		elseif($data['user_avatar_type'] == 2 || $data['user_avatar_type'] == 'avatar.driver.remote')
		{
			$insert_data['avatartype'] = AVATAR_TYPE_URL;
			$insert_data['avatar'] = $data['user_avatar'];
			$insert_data['avatardimensions'] = "{$data['user_avatar_height']}|{$data['user_avatar_width']}";
		}
		elseif($data['user_avatar_type'] == 'avatar.driver.gravatar')
		{
			$insert_data['avatartype'] = AVATAR_TYPE_GRAVATAR;
			$insert_data['avatar'] = $this->get_gravatar_url($data['user_avatar']);

			if(!$mybb->settings['maxavatardims'])
			{
				$mybb->settings['maxavatardims'] = '100x100'; // Hard limit of 100 if there are no limits
			}
			list($maxwidth, $maxheight) = explode("x", my_strtolower($mybb->settings['maxavatardims']));
			$insert_data['avatardimensions'] = "{$maxheight}|{$maxwidth}";
		}

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of users with avatar
		if(!isset($import_session['total_avatars']))
		{
			$query = $this->old_db->simple_select("users", "COUNT(*) as count", "user_id > 0 AND username != 'Anonymous' AND group_id != 6");
			$import_session['total_avatars'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_avatars'];
	}

	function generate_raw_filename($data)
	{
		// Database value: {id}_{timestamp}.{ext}
		// Filename: {salt}_{id}.{ext}

		if(empty($this->avatar_salt))
		{
			$query = $this->old_db->simple_select("config", "config_value", "config_name = 'avatar_salt'", array('limit' => 1));
			$this->avatar_salt = $this->old_db->fetch_field($query, 'config_value');
			$this->old_db->free_result($query);
		}

		$id = my_substr($data['user_avatar'], 0, strpos($data['user_avatar'], '_'));
		$ext = get_extension($data['user_avatar']);

		return "{$this->avatar_salt}_{$id}.{$ext}";
	}
}
