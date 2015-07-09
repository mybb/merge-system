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

class SMF_Converter_Module_Avatars extends Converter_Module_Avatars {

	var $settings = array(
		'friendly_name' => 'avatars',
		'progress_column' => 'ID_MEMBER',
		'default_per_screen' => 20,
	);

	function get_avatar_path()
	{
		$query = $this->old_db->simple_select("settings", "value", "variable = 'attachmentUploadDir'", array('limit' => 1));
		$uploadspath = $this->old_db->fetch_field($query, 'value');
		$this->old_db->free_result($query);
		return $uploadspath;
	}

	function import()
	{
		global $import_session;

		$query = $this->old_db->query("SELECT u.ID_MEMBER, u.avatar, a.ID_ATTACH, a.filename, a.file_hash, a.width, a.height
			FROM ".OLD_TABLE_PREFIX."members u
			LEFT JOIN ".OLD_TABLE_PREFIX."attachments a ON (a.ID_MEMBER=u.ID_MEMBER AND ID_MSG=0)
			WHERE u.avatar != '' OR a.ID_ATTACH IS NOT NULL
			LIMIT {$this->trackers['start_avatars']}, {$import_session['avatars_per_screen']}");
		while($avatar = $this->old_db->fetch_array($query))
		{
			$this->insert($avatar);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// MyBB 1.8 values
		$insert_data['uid'] = $this->get_import->uid($data['ID_MEMBER']);

		if(!empty($data['avatar']))
		{
			$insert_data['avatartype'] = AVATAR_TYPE_URL;
			$insert_data['avatar'] = $data['avatar'];

			$img_size = getimagesize($data['avatar']);
			$insert_data['avatardimensions'] = "{$img_size[1]}|{$img_size[0]}";
		}
		else
		{
			$insert_data['avatar'] = $this->get_upload_avatar_name($insert_data['uid'], $data['filename']);
			$insert_data['avatartype'] = AVATAR_TYPE_UPLOAD;
			$insert_data['avatardimensions'] = "{$data['height']}|{$data['width']}";
		}

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of users with avatar
		if(!isset($import_session['total_avatars']))
		{
			$query = $this->old_db->query("SELECT u.ID_MEMBER, u.avatar, a.ID_ATTACH, a.filename, a.file_hash, a.width, a.height
				FROM ".OLD_TABLE_PREFIX."members u
				LEFT JOIN ".OLD_TABLE_PREFIX."attachments a ON (a.ID_MEMBER=u.ID_MEMBER AND ID_MSG=0)
				WHERE u.avatar != '' OR a.ID_ATTACH IS NOT NULL");
			$import_session['total_avatars'] = $this->old_db->num_rows($query);
			$this->old_db->free_result($query);
		}

		return $import_session['total_avatars'];
	}

	function generate_raw_filename($avatar)
	{
		return $avatar['ID_ATTACH']."_".$avatar['file_hash'];
	}
}
