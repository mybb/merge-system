<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 *
 * IPS5 Settings Converter - SKELETON
 * TODO: Verify all table and column names against a real IPS5 database
 * IPS5 may store settings differently than IPS4 (Laravel config vs core_sys_conf_settings)
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class IPB5_Converter_Module_Settings extends Converter_Module_Settings {

	var $settings = array(
		'friendly_name' => 'settings',
		'default_per_screen' => 1000,
	);

	// TODO: Verify IPS5 settings are still in core_sys_conf_settings with conf_key/conf_value/conf_default columns
	// IPS5 may use a different settings storage mechanism
	var $convert_settings = array(
			"board_offline" => "boardclosed",
			"offline_msg" => "boardclosed_reason",
			"au_cutoff" => "wolcutoffmins",
			"how_totals" => "showindexstats",
			"show_active" => "showwol",
			"load_limit" => "load",
			"disable_subforum_show" => "subforumsindex",
			"mail_method" => "mail_handler",
			"smtp_host" => "smtp_host",
			"smtp_port" => "smtp_port",
			"smtp_user" => "smtp_user",
			"smtp_pass" => "smtp_pass",
			"php_mail_extra" => "mail_parameters",
			"csite_pm_show" => "portal_showwelcome",
			"csite_search_show" => "portal_showsearch",
			"msg_allow_code" => "pmsallowmycode",
			"msg_allow_html" => "pmsallowhtml",
			"search_sql_method" => "searchtype",
			"min_search_word" => "minsearchword",
			"display_max_topics" => "threadsperpage",
			"hot_topic" => "hottopic",
			"display_max_posts" => "postsperpage",
			"max_images" => "maxpostimages",
			"siu_thumb" => "attachthumbnails",
			"siu_width" => "attachthumbw",
			"siu_height" => "attachthumbh",
			"max_poll_choices" => "maxpolloptions",
			"post_wordwrap" => "wordwrap",
			"max_sig_length" => "siglength",
			"sig_allow_html" => "sightml",
			"sig_allow_ibc" => "sigmycode",
			"postpage_contents" => "userpppoptions",
			"topicpage_contents" => "usertppoptions",
			"avup_size_max" => "avatarsize",
			"avatar_dims" => "maxavatardims",
			"ipb_bruteforce_attempts" => "failedlogincount",
			"ipb_bruteforce_period" => "failedlogintime",
			"no_reg" => "disableregs",
			"flood_control" => "postfloodsecs",
			"ipb_display_version" => "showvernum",
		);

	function import()
	{
		global $import_session;

		// TODO: Verify core_sys_conf_settings table exists in IPS5
		$query = $this->old_db->simple_select("core_sys_conf_settings", "conf_key, conf_value, conf_default", "conf_key IN('".implode("','", array_keys($this->convert_settings))."')", array('limit_start' => $this->trackers['start_settings'], 'limit' => $import_session['settings_per_screen']));
		while($setting = $this->old_db->fetch_array($query))
		{
			// Invision Community 5 values
			$name = $this->convert_settings[$setting['conf_key']];

			if(empty($setting['conf_value']))
			{
				$value = $setting['conf_default'];
			}
			else
			{
				$value = $setting['conf_value'];
			}

			if($setting['conf_key'] == "disable_subforum_show")
			{
				if($value == "on")
				{
					$value = "1000";
				}
				else
				{
					$value = 0;
				}
			}

			if($setting['conf_key'] == "search_sql_method")
			{
				if($value == "ftext")
				{
					$value = "fulltext";
				}
				else
				{
					$value = "standard";
				}
			}

			$this->update_setting($name, $value);
		}
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of settings
		if(!isset($import_session['total_settings']))
		{
			$query = $this->old_db->simple_select("core_sys_conf_settings", "COUNT(*) as count", "conf_key IN('".implode("','", array_keys($this->convert_settings))."')");
			$import_session['total_settings'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_settings'];
	}
}
