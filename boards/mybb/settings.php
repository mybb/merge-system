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

class MYBB_Converter_Module_Settings extends Converter_Module_Settings {

	var $settings = array(
		'friendly_name' => 'settings',
		'default_per_screen' => 1000,
	);

	var $convert_ignore_settings = array('bbname', 'bburl', 'homename', 'homeurl', 'adminemail', 'contactlink', 'cookiedomain', 'cookiepath');

	function import()
	{
		global $mybb, $output, $import_session, $db;

		$query = $this->old_db->simple_select("settings", "name, value", "name NOT IN('".implode("','", $this->convert_ignore_settings)."') AND isdefault='1'", array('limit_start' => $this->trackers['start_settings'], 'limit' => $import_session['settings_per_screen']));
		while($setting = $this->old_db->fetch_array($query))
		{
			$this->update_setting($setting['name'], $setting['value']);
		}
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of settings
		if(!isset($import_session['total_settings']))
		{
			$query = $this->old_db->simple_select("settings", "COUNT(*) as count", "name NOT IN('".implode("','", $this->convert_ignore_settings)."') AND isdefault='1'");
			$import_session['total_settings'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_settings'];
	}

	function finish()
	{
		rebuild_settings();
	}
}

?>