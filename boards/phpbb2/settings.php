<?php
/**
 * MyBB 1.6
 * Copyright 2009 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class PHPBB2_Converter_Module_Settings extends Converter_Module_Settings {

	var $settings = array(
		'friendly_name' => 'settings',
		'default_per_screen' => 1000,
	);

	// What settings do we need to get and what is their MyBB equivalent?
	var $convert_settings = array(
			"max_sig_chars" => "siglength",
			"avatar_max_height" => "maxavatardims",
			"avatar_max_width" => "maxavatardims",
			"hot_threshold" => "hottopic",
			"max_poll_options" => "maxpolloptions",
			"privmsg_disable" => "enablepms",
			"board_timezone" => "timezoneoffset",
			"avatar_gallery_path" => "avatardir",
			"posts_per_page" => "postsperpage",
			"topics_per_page" => "threadsperpage",
			"flood_interval" => "postfloodsecs",
			"search_flood_interval" => "searchfloodtime",
			"search_min_chars" => "minsearchword",
			"enable_confirm" => "captchaimage",
			"avatar_filesize" => "avatarsize",
			"max_login_attempts" => "failedlogincount",
			"login_reset_time" => "failedlogintime",
			"gzip_compress" => "gzipoutput"
		);

	function import()
	{
		global $import_session;

		$int_to_yes_no = array(
			"privmsg_disable" => 0
		);

		$query = $this->old_db->simple_select("config", "config_name, config_value", "config_name IN('".implode("','", array_keys($this->convert_settings))."')", array('limit_start' => $this->trackers['start_settings'], 'limit' => $import_session['settings_per_screen']));
		while($setting = $this->old_db->fetch_array($query))
		{
			// phpBB 2 values
			$name = $this->convert_settings[$setting['config_name']];
			$value = $setting['config_value'];

			if($setting['config_name'] == "avatar_max_height")
			{
				if(isset($avatar_width))
				{
					$value = $avatar_width."x".$value;
					unset($avatar_width);
				}
				else
				{
					$avatar_height = $value;
					continue;
				}
			}
			if($setting['config_name'] == "avatar_max_width")
			{
				if(isset($avatar_height))
				{
					$value = $value."x".$avatar_height;
					unset($avatar_height);
				}
				else
				{
					$avatar_width = $value;
					continue;
				}
			}

			if($setting['config_name'] == "avatar_filesize")
			{
				$value = ceil($value / 1024);
			}

			if(($value == 0 || $value == 1) && isset($int_to_yes_no[$setting['config_name']]))
			{
				$value = int_to_yes_no($value, $int_to_yes_no[$setting['config_name']]);
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