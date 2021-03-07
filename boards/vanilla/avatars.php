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

class VANILLA_Converter_Module_Avatars extends Converter_Module_Avatars {

	var $settings = array(
		'friendly_name' => 'avatars',
		'progress_column' => 'UserID',
		'default_per_screen' => 20,
	);

	function get_avatar_path()
	{
		return "/uploads/";
	}

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select("user", "*", "Photo != '' AND Name != 'System'", array('limit_start' => $this->trackers['start_avatars'], 'limit' => $import_session['avatars_per_screen']));
		while($avatar = $this->old_db->fetch_array($query))
		{
			$this->insert($avatar);
		}
	}

	function convert_data($data)
	{
		global $import_session;

		$insert_data = array();

		// MyBB 1.8 values
		$insert_data['uid'] = $this->get_import->uid($data['UserID']);

		$insert_data['avatar'] = $this->get_upload_avatar_name($insert_data['uid'], $data['Photo']);
		$insert_data['avatartype'] = AVATAR_TYPE_UPLOAD;

		if (stripos($data['Photo'], 's3://') === 0) {
			$data['Photo'] = ltrim(substr($data['Photo'], 5));

			if (defined('VANILLA_S3_BASE_URL')) {
				$url = rtrim(VANILLA_S3_BASE_URL, '/') . '/' . $data['Photo'];

				// download the content of the avatar
				$fileContent = fetch_remote_file($url);

				if (false === $fileContent) {
					return array();
				}

				// save the avatar locally
				$temp_dir = sys_get_temp_dir() . '/vanilla_import_avatars';

				if (false === mkdir($temp_dir)) {
					return array();
				}

				$dest_file = tempnam($temp_dir, basename($data['Photo']));

				if (false === $dest_file) {
					return array();
				}

				if (file_put_contents($dest_file, $fileContent) === false) {
					return array();
				}
			}

			return array();
		}

		$img_size = getimagesize($import_session['avatarspath'].$this->generate_raw_filename($data));
		$insert_data['avatardimensions'] = "{$img_size[1]}|{$img_size[0]}";

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of users with avatar
		if(!isset($import_session['total_avatars']))
		{
			$query = $this->old_db->simple_select("user", "COUNT(*) as count", "Photo != '' AND Name != 'System'");
			$import_session['total_avatars'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_avatars'];
	}

	function generate_raw_filename($avatar)
	{
		$name = basename($avatar['Photo']);
		$dir = substr($avatar['Photo'], 0, -strlen($name));
		return "{$dir}p{$name}"; // Yeah, we need to add a "p" here...
	}
}
