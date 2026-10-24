<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 *
 * Discourse Avatar Converter
 * Discourse stores avatars in user_avatars linking to uploads.
 * Avatars can be custom uploads or Gravatar.
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class DISCOURSE_Converter_Module_Avatars extends Converter_Module_Avatars {

	var $settings = array(
		'friendly_name' => 'avatars',
		'progress_column' => 'user_id',
		'default_per_screen' => 20,
	);

	function get_avatar_path()
	{
		global $mybb;

		// Discourse uploads are stored at /uploads/default/original/1X/{sha1}.{ext}
		// The full URL is needed to download them
		$mybb->input['uploadspath'] = "";
		return "";
	}

	function import()
	{
		global $import_session;

		$query = $this->old_db->query("
			SELECT ua.*, u.email, u2.url as upload_url, u2.original_filename, u2.extension
			FROM ".OLD_TABLE_PREFIX."user_avatars ua
			LEFT JOIN ".OLD_TABLE_PREFIX."users u ON (u.id = ua.user_id)
			LEFT JOIN ".OLD_TABLE_PREFIX."uploads u2 ON (u2.id = ua.custom_upload_id)
			LIMIT ".$import_session['avatars_per_screen']." OFFSET ".$this->trackers['start_avatars']."
		");
		while($avatar = $this->old_db->fetch_array($query))
		{
			$this->insert($avatar);
		}
	}

	function convert_data($data)
	{
		global $mybb;

		$insert_data = array();
		$insert_data['uid'] = $this->get_import->uid($data['user_id']);

		// Custom upload avatar
		if(!empty($data['custom_upload_id']) && !empty($data['upload_url']))
		{
			$insert_data['avatartype'] = AVATAR_TYPE_UPLOAD;
			$insert_data['avatar'] = $this->get_upload_avatar_name($insert_data['uid'], $data['upload_url']);

			if(!$mybb->settings['maxavatardims'])
			{
				$mybb->settings['maxavatardims'] = '100x100';
			}
			list($maxwidth, $maxheight) = explode("x", my_strtolower($mybb->settings['maxavatardims']));
			$insert_data['avatardimensions'] = "{$maxheight}|{$maxwidth}";
		}
		// Gravatar
		elseif(!empty($data['gravatar_upload_id']) || $this->check_gravatar_exists($data['email']))
		{
			$insert_data['avatartype'] = AVATAR_TYPE_GRAVATAR;
			$insert_data['avatar'] = $this->get_gravatar_url($data['email']);

			if(!$mybb->settings['maxavatardims'])
			{
				$mybb->settings['maxavatardims'] = '100x100';
			}
			list($maxwidth, $maxheight) = explode("x", my_strtolower($mybb->settings['maxavatardims']));
			$insert_data['avatardimensions'] = "{$maxheight}|{$maxwidth}";
		}

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		if(!isset($import_session['total_avatars']))
		{
			$query = $this->old_db->simple_select("user_avatars", "COUNT(*) as count");
			$import_session['total_avatars'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_avatars'];
	}

	function generate_raw_filename($data)
	{
		return $data['upload_url'];
	}

	function print_avatars_per_screen_page() {}
}
