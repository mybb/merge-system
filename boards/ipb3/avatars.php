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

class IPB3_Converter_Module_Avatars extends Converter_Module_Avatars {

	var $settings = array(
		'friendly_name' => 'members',
		'progress_column' => 'member_id',
		'default_per_screen' => 20,
	);

	function get_avatar_path()
	{
		$query = $this->old_db->simple_select("core_sys_conf_settings", "conf_value", "conf_key = 'upload_url'", array('limit' => 1));
		$uploadspath = $this->old_db->fetch_field($query, 'conf_value');
		$this->old_db->free_result($query);
		return $uploadspath;
	}

	function import()
	{
		global $import_session;

		$query = $this->old_db->query("
			SELECT *
			FROM ".OLD_TABLE_PREFIX."members m
			LEFT JOIN ".OLD_TABLE_PREFIX."profile_portal pp ON (m.member_id=pp.pp_member_id)
			LIMIT ".$this->trackers['start_avatars'].", ".$import_session['avatars_per_screen']
		);
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
		$insert_data['uid'] = $this->get_import->uid($data['member_id']);

		if($data['pp_photo_type'] == 'custom')
		{
			$insert_data['avatartype'] = AVATAR_TYPE_UPLOAD;
			$insert_data['avatar'] = $this->get_upload_avatar_name($insert_data['uid'], $data['pp_main_photo']);
			$insert_data['avatardimensions'] = "{$data['pp_main_height']}|{$data['pp_main_width']}";
		}
		elseif($data['pp_photo_type'] == 'gravatar' || $this->check_gravatar_exists($data['email']))
		{
			$insert_data['avatartype'] = AVATAR_TYPE_GRAVATAR;

			if($data['pp_photo_type'] == 'gravatar')
			{
				$email = $data['pp_gravatar'];
			}
			else
			{
				$email = $data['email'];
			}
			$insert_data['avatar'] = $this->get_gravatar_url($email);

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
			$query = $this->old_db->simple_select("members", "COUNT(*) as count");
			$import_session['total_avatars'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_avatars'];
	}

	function generate_raw_filename($avatar)
	{
		return $avatar['pp_main_photo'];
	}
}
