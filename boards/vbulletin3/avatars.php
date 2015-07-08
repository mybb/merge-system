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

class VBULLETIN3_Converter_Module_Avatars extends Converter_Module_Avatars {

	var $settings = array(
		'friendly_name' => 'customavatar',
		'progress_column' => 'userid',
		'default_per_screen' => 20,
	);

	function pre_setup()
	{
		// No need for an upload path, vb saves the complete file(!!!) in the database
		$this->check_avatar_dir_perms();
	}

	function get_avatar_path() {}

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select("customavatar", "*", "", array('limit_start' => $this->trackers['start_avatars'], 'limit' => $import_session['avatars_per_screen']));
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
		$insert_data['uid'] = $this->get_import->uid($data['userid']);

		$insert_data['avatar'] = $this->get_upload_avatar_name($insert_data['uid'], $data['filename']);
		$insert_data['avatartype'] = AVATAR_TYPE_UPLOAD;
		$insert_data['avatardimensions'] = "{$data['height']}|{$data['width']}";

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of users with avatar
		if(!isset($import_session['total_avatars']))
		{
			$query = $this->old_db->simple_select("customavatar", "COUNT(*) as count");
			$import_session['total_avatars'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_avatars'];
	}

	function after_insert($data, $insert_data, $aid)
	{
		global $lang;

		// Transfer avatar
		if(substr($insert_data['avatar'], 0, 2) == "./" || substr($insert_data['avatar'], 0, 3) == "../")
		{
			$insert_data['avatar'] = MYBB_ROOT.$insert_data['avatar'];
		}
		$insert_data['avatar'] = my_substr($insert_data['avatar'], 0, strpos($insert_data['avatar'], '?'));
		$file = @fopen($insert_data['avatar'], 'w');
		if($file)
		{
			@fwrite($file, $data['filedata']);
		}
		else
		{
			$this->board->set_error_notice_in_progress($lang->sprintf($lang->module_attachment_error, $aid));
		}
		@fclose($file);
		@my_chmod($insert_data['avatar'], '0777');
	}

	function generate_raw_filename($avatar)
	{
		return $avatar['filename'];
	}

	function print_avatars_per_screen_page() {}
}
