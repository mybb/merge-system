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

class XENFORO_Converter_Module_Avatars extends Converter_Module_Avatars {

	var $settings = array(
		'friendly_name' => 'user',
		'progress_column' => 'user_id',
		'default_per_screen' => 20,
	);

	function get_avatar_path()
	{
		$query = $this->old_db->simple_select("option", "option_value", "option_id='boardUrl'");
		$uploadspath = $this->old_db->fetch_field($query, "option_value") . "/data/avatars/";
		$this->old_db->free_result($query);
		return $uploadspath;
	}

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select("user", "*", "avatar_date > 0 OR gravatar != ''", array('limit_start' => $this->trackers['start_avatars'], 'limit' => $import_session['avatars_per_screen']));
		while($avatar = $this->old_db->fetch_array($query))
		{
			$this->insert($avatar);
		}
	}

	function convert_data($data)
	{
		global $insert_data, $mybb;

		$insert_data = array();

		// MyBB 1.8 values
		$insert_data['uid'] = $this->get_import->uid($data['user_id']);

		if(!empty($data['gravatar']))
		{
			$insert_data['avatartype'] = AVATAR_TYPE_GRAVATAR;
			$insert_data['avatar'] = $this->get_gravatar_url($data['gravatar']);

			if(!$mybb->settings['maxavatardims'])
			{
				$mybb->settings['maxavatardims'] = '100x100'; // Hard limit of 100 if there are no limits
			}

			list($maxwidth, $maxheight) = explode("x", my_strtolower($mybb->settings['maxavatardims']));
			$this->dimension = "{$maxheight}|{$maxwidth}";
		}
		else
		{
			$insert_data['avatar'] = $this->get_upload_avatar_name($insert_data['uid'], $this->generate_raw_filename($data));
			$insert_data['avatartype'] = AVATAR_TYPE_UPLOAD;
			$insert_data['avatardimensions'] = "{$data['avatar_height']}|{$data['avatar_width']}";
		}

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of users with avatar
		if(!isset($import_session['total_avatars']))
		{
			$query = $this->old_db->simple_select("user", "COUNT(*) as count", "avatar_date > 0 OR gravatar != ''");
			$import_session['total_avatars'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_avatars'];
	}

	function generate_raw_filename($avatar)
	{
		return "l/".floor($avatar['user_id']/1000)."/{$avatar['user_id']}.jpg";
	}
}
