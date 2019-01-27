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
		'friendly_name' => 'avatars',
		'progress_column' => 'userid',
		'default_per_screen' => 20,
	);

	var $use_filesystem;

	function pre_setup()
	{
		global $mybb, $import_session;

		$query = $this->old_db->simple_select('setting', 'value', "varname='usefileavatar'");
		$this->use_filesystem = $this->old_db->fetch_field($query, 'value');
		$this->old_db->free_result($query);

		// File is saved in the database, no need for an uploadspath!
		if(!$this->use_filesystem)
		{
			$import_session['avatarspath'] = '';
			unset($mybb->input['avatarspath']);
		}

		parent::pre_setup();
	}

	function get_avatar_path()
	{
		$query = $this->old_db->simple_select('setting', 'value', "varname='bburl'");
		$uploadspath = $this->old_db->fetch_field($query, 'value') . '/';
		$this->old_db->free_result($query);

		$query = $this->old_db->simple_select('setting', 'value', "varname='avatarurl'");
		$uploadspath .= $this->old_db->fetch_field($query, 'value');
		$this->old_db->free_result($query);

		return $uploadspath;
	}

	function import()
	{
		global $import_session;

		$query = $this->old_db->query("SELECT a.*, u.avatarrevision AS revision
			FROM ".OLD_TABLE_PREFIX."customavatar a
			LEFT JOIN ".OLD_TABLE_PREFIX."user u ON (u.userid = a.userid)
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
		$insert_data['uid'] = $this->get_import->uid($data['userid']);

		$insert_data['avatar'] = $this->get_upload_avatar_name($insert_data['uid'], $data['filename']);
		$insert_data['avatartype'] = AVATAR_TYPE_UPLOAD;
		$insert_data['avatardimensions'] = "{$data['width']}|{$data['height']}";

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

	/**
	 * Get the raw file data. vBulletin saves the full data in the database by default!
	 *
	 * @param array $unconverted_data
	 *
	 * @return string
	 */
	function get_file_data($unconverted_data)
	{
		if(!$this->use_filesystem)
		{
			return $unconverted_data['filedata'];
		}
		return parent::get_file_data($unconverted_data);
	}

	function generate_raw_filename($avatar)
	{
		// Yep, gif is hardcoded
		return "avatar" . $avatar['userid'] . "_" . $avatar['revision'] . ".gif";
	}

	function print_avatars_per_screen_page()
	{
		if($this->use_filesystem)
		{
			parent::print_avatars_per_screen_page();
		}
	}
}
