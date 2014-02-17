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

class PHPBB3_Converter_Module_Settings extends Converter_Module_Settings {

	var $settings = array(
		'friendly_name' => 'settings',
		'default_per_screen' => 1000,
	);

	// What settings do we need to get and what is their MyBB equivalent?
	var $convert_settings = array(
			"avatar_max_height" => "maxavatardims",
			"avatar_max_width" => "maxavatardims",
			"avatar_gallery_path" => "avatardir",
			"avatar_filesize" => "avatarsize",
			"max_sig_chars" => "siglength",
			"hot_threshold" => "hottopic",
			"max_poll_options" => "maxpolloptions",
			"allow_privmsg" => "enablepms",
			"board_timezone" => "timezoneoffset",
			"posts_per_page" => "postsperpage",
			"topics_per_page" => "threadsperpage",
			"flood_interval" => "postfloodsecs",
			"search_interval" => "searchfloodtime",
			"min_search_author_chars" => "minsearchword",
			"enable_confirm" => "captchaimage",
			"max_login_attempts" => "failedlogincount",
			"gzip_compress" => "gzipoutput",
			"search_type" => "searchtype",
			"smtp_host"		=> "smtp_host",
			"smtp_password"	=> "smtp_pass",
			"smtp_port"		=> "smtp_port",
			"smtp_username"	=> "smtp_user",
			"smtp_delivery" => "mail_handler"
		);

	function import()
	{
		global $import_session;

		$int_to_yes_no = array(
			"allow_privmsg" => 1,
			"gzip_compress" => 1
		);

		$int_to_on_off = array(
			"enable_confirm" => 1
		);

		$query = $this->old_db->simple_select("config", "config_name, config_value", "config_name IN('".implode("','", array_keys($this->convert_settings))."')", array('limit_start' => $this->trackers['start_settings'], 'limit' => $import_session['settings_per_screen']));
		while($setting = $this->old_db->fetch_array($query))
		{
			// phpBB 3 values
			$name = $this->convert_settings[$setting['config_name']];
			$value = $setting['config_value'];

			if($setting['config_name'] == "avatar_max_height")
			{
				$avatar_setting = "x".$value;
				continue;
			}
			else if($setting['config_name'] == "avatar_max_width")
			{
				$value = $value.$avatar_setting;
				unset($avatar_setting);
			}

			if($setting['config_name'] == "avatar_filesize")
			{
				$value = ceil($value / 1024);
			}

			if(($value == 0 || $value == 1) && isset($int_to_yes_no[$setting['config_name']]))
			{
				$value = int_to_yes_no($value, $int_to_yes_no[$setting['config_name']]);
			}

			if(($value == 0 || $value == 1) && isset($int_to_on_off[$setting['config_name']]))
			{
				$value = int_to_on_off($value, $int_to_on_off[$setting['config_name']]);
			}

			if($setting['config_name'] == 'search_type')
			{
				$value = "fulltext";
			}

			if($setting['config_name'] == 'board_timezone')
			{
				if(strpos($value, '-') === false && $value != 0)
				{
					$value = "+".$value;
				}
			}

			if($setting['config_name'] == "smtp_delivery" && $value == 1)
			{
				$value = "smtp";
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
			$query = $this->old_db->simple_select("config", "COUNT(*) as count", "config_name IN('".implode("','", array_keys($this->convert_settings))."')");
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