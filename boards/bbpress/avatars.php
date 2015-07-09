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

class BBPRESS_Converter_Module_Avatars extends Converter_Module_Avatars {

	var $settings = array(
		'friendly_name' => 'avatars',
		'progress_column' => 'ID',
		'default_per_screen' => 20,
	);

	var $dimension;

	function get_avatar_path() {}

	function pre_setup()
	{
		global $mybb, $import_session;

		// Always check whether we can write to our own directory first
		$this->check_avatar_dir_perms();

		$import_session['avatarspath'] = '';

		if(!$mybb->settings['maxavatardims'])
		{
			$mybb->settings['maxavatardims'] = '100x100'; // Hard limit of 100 if there are no limits
		}

		list($maxwidth, $maxheight) = explode("x", my_strtolower($mybb->settings['maxavatardims']));
		$this->dimension = "{$maxheight}|{$maxwidth}";
	}

	function print_avatars_per_screen_page() {}

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select("users", "*", "user_email!=''", array('limit_start' => $this->trackers['start_avatars'], 'limit' => $import_session['avatars_per_screen']));
		while($avatar = $this->old_db->fetch_array($query))
		{
			$this->insert($avatar);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// MyBB 1.8 values
		$insert_data['uid'] = $this->get_import->uid($data['ID']);

		if($this->check_gravatar_exists($data['user_email'])) {
			$insert_data['avatar'] = $this->get_gravatar_url($data['user_email']);
			$insert_data['avatartype'] = AVATAR_TYPE_GRAVATAR;
			$insert_data['avatardimensions'] = $this->dimension;
		}

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of users with avatar
		if(!isset($import_session['total_avatars']))
		{
			$query = $this->old_db->simple_select("users", "COUNT(*) as count", "user_email!=''");
			$import_session['total_avatars'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_avatars'];
	}

	function generate_raw_filename($avatar) {}
}


