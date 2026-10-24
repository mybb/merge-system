<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 *
 * Discourse Settings Converter
 * Discourse stores settings in the `site_settings` table as key-value pairs.
 * We map a subset of relevant Discourse settings to MyBB equivalents.
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class DISCOURSE_Converter_Module_Settings extends Converter_Module_Settings {

	var $settings = array(
		'friendly_name' => 'settings',
		'default_per_screen' => 100,
	);

	var $convert_settings = array(
		"title" => "bbname",
		"site_description" => "bbdesc",
		"contact_email" => "adminemail",
		"site_contact_username" => "adminemail",
		"notification_email" => "mailingaddress",
		"max_topic_title_length" => "maxnamelength",
		"min_topic_title_length" => "minnamelength",
		"min_post_length" => "minmessagelength",
		"max_post_length" => "maxmessagelength",
		"min_private_message_post_length" => "pmsminmessagelength",
		"max_private_message_post_length" => "pmsmaxmessagelength",
		"topics_per_page" => "threadsperpage",
		"posts_per_page" => "postsperpage",
		"max_image_size_kb" => "maxpostimages",
		"max_attachment_size_kb" => "attachthumbnails",
	);

	function import()
	{
		global $import_session;

		if(!$this->old_db->table_exists("site_settings"))
		{
			$import_session['total_settings'] = 0;
			return;
		}

		$query = $this->old_db->simple_select("site_settings", "name, value", "name IN('".implode("','", array_keys($this->convert_settings))."')", array('limit_start' => $this->trackers['start_settings'], 'limit' => $import_session['settings_per_screen']));
		while($setting = $this->old_db->fetch_array($query))
		{
			$name = $this->convert_settings[$setting['name']];
			$value = $setting['value'];

			// Convert KB to bytes for attachment sizes
			if($setting['name'] == 'max_image_size_kb' || $setting['name'] == 'max_attachment_size_kb')
			{
				$value = (int)$value * 1024;
			}

			$this->update_setting($name, $value);
		}
	}

	function fetch_total()
	{
		global $import_session;

		if(!isset($import_session['total_settings']))
		{
			if(!$this->old_db->table_exists("site_settings"))
			{
				$import_session['total_settings'] = 0;
			}
			else
			{
				$query = $this->old_db->simple_select("site_settings", "COUNT(*) as count", "name IN('".implode("','", array_keys($this->convert_settings))."')");
				$import_session['total_settings'] = $this->old_db->fetch_field($query, 'count');
				$this->old_db->free_result($query);
			}
		}

		return $import_session['total_settings'];
	}
}
