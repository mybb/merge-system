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

class IPB4_Converter_Module_Avatars extends Converter_Module_Avatars {

	var $settings = array(
		'friendly_name' => 'members',
		'progress_column' => 'member_id',
		'default_per_screen' => 20,
	);

	function get_avatar_path()
	{
		global $mybb;
		// IPB 4 seems to save the full location - reset the input so the we check whether we can read the attachments
		$mybb->input['uploadspath'] = "";
		return "";
	}

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select("core_members", "*", "", array('limit_start' => $this->trackers['start_avatars'], 'limit' => $import_session['avatars_per_screen']));
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

			if(!empty($data['pp_main_height']) && !empty($data['pp_main_width']))
			{
				$insert_data['avatardimensions'] = "{$data['pp_main_height']}|{$data['pp_main_width']}";
			}
			else
			{
				$img_size = getimagesize($this->generate_raw_filename($data));
				$insert_data['avatardimensions'] = "{$img_size[1]}|{$img_size[0]}";
			}
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
			$query = $this->old_db->simple_select("core_members", "COUNT(*) as count");
			$import_session['total_avatars'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_avatars'];
	}

	function generate_raw_filename($data)
	{
		$url = $data['pp_main_photo'];

		if(substr($url, 0, 2) == '//')
		{
			return "http:".$url;
		}

		return $url;
	}

	// Overwrite parent function. As the full path is saved we don't need to ask for it
	function print_avatars_per_screen_page() {}
}
