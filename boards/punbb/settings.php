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

class PUNBB_Converter_Module_Settings extends Converter_Module_Settings {

	var $settings = array(
		'friendly_name' => 'settings',
		'default_per_screen' => 1000,
	);

	// What settings do we need to get and what is their MyBB equivalent?
	var $convert_settings = array(
			"o_server_timezone" => "timezoneoffset",
			"o_time_format" => "timeformat",
			"o_date_format" => "dateformat",
			"o_timeout_online" => "wolcutoffmins",
			"o_show_version" => "showvernum",
			"o_smilies_sig" => "sigsmilies",
			"o_disp_topics_default" => "threadsperpage",
			"o_disp_posts_default" => "postsperpage",
			"o_quickpost" => "quickreply",
			"o_users_online" => "showwol",
			"o_show_dot" => "dotfolders",
			"o_gzip" => "gzipoutput",
			"o_avatars_height" => "maxavatardims",
			"o_avatars_width" => "maxavatardims",
			"o_avatars_size" => "avatarsize",
			"o_smtp_host" => "smtp_host",
			"o_smtp_user" => "smtp_user",
			"o_smtp_pass" => "smtp_pass",
			"o_regs_allow" => "disableregs",
			"o_regs_verify" => "regtype",
			"o_maintenance" => "boardclosed",
			"o_maintenance_message" => "boardclosed_reason",
			"p_sig_bbcode" => "sigmycode",
			"p_sig_img_tag" => "sigimgcode",
			"p_sig_length" => "siglength"
		);

	function import()
	{
		global $import_session;

		$int_to_yes_no = array(
			"o_show_version" => 1,
			"o_smilies_sig" => 1,
			"o_quickpost" => 1,
			"o_users_online" => 1,
			"o_show_dot" => 1,
			"o_gzip" => 1,
			"o_regs_allow" => 0,
			"o_maintenance" => 1,
			"p_sig_bbcode" => 1,
			"p_sig_img_tag" => 1
		);

		$query = $this->old_db->simple_select("config", "conf_name, conf_value", "conf_name IN('".implode("','", array_keys($this->convert_settings))."')", array('limit_start' => $this->trackers['start_settings'], 'limit' => $import_session['settings_per_screen']));
		while($setting = $this->old_db->fetch_array($query))
		{
			// punBB 1 values
			$name = $this->convert_settings[$setting['conf_name']];
			$value = $setting['conf_value'];

			if($setting['conf_name'] == "o_timeout_online")
			{
				$value = ceil($value / 60);
			}

			if($setting['conf_name'] == "o_server_timezone")
			{
				$value = str_replace(".5", "", $value);
			}

			if($setting['conf_name'] == "o_avatars_width")
			{
				$avatar_setting = $value."x";
				echo "done.<br />\n";
				continue;
			}

			if($setting['conf_name'] == "o_avatars_height")
			{
				$value = $avatar_setting.$value;
				unset($avatar_setting);
			}

			if($setting['conf_name'] == "o_avatars_size")
			{
				$value = ceil($value / 1024);
			}

			if($setting['conf_name'] == "o_regs_verify")
			{
				if($value == 0)
				{
					$value = "randompass";
				}
				else
				{
					$value = "verify";
				}
			}

			if($setting['conf_name'] == "o_quickpost")
			{
				if($value == 0)
				{
					$value = 0;
				}
				else
				{
					$value = 1;
				}
			}

			if(($value == 0 || $value == 1) && isset($int_to_yes_no[$setting['conf_name']]))
			{
				$value = int_to_yes_no($value, $int_to_yes_no[$setting['conf_name']]);
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
			$query = $this->old_db->simple_select("config", "COUNT(*) as count", "conf_name IN('".implode("','", array_keys($this->convert_settings))."')");
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