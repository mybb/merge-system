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

class SMF2_Converter_Module_Settings extends Converter_Module_Settings {

	var $settings = array(
		'friendly_name' => 'settings',
		'default_per_screen' => 1000,
	);

	// What settings do we need to get and what is their MyBB equivalent?
	var $convert_settings = array(
			"karmaMode" => "enablereputation",
			"enableCompressedOutput" => "gzipoutput",
			"attachmentNumPerPostLimit" => "maxattachments",
			"attachmentThumbnails" => "attachthumbnails",
			"attachmentThumbWidth" => "attachthumbw",
			"attachmentThumbHeight" => "attachthumbh",
			"enableErrorLogging" => "useerrorhandling",
			"cal_enabled" => "enablecalendar",
			"smtp_host" => "smtp_host",
			"smtp_port" => "smtp_port",
			"smtp_username" => "smtp_user",
			"smtp_password" => "smtp_pass",
			"mail_type" => "mail_handler",
			"hotTopicPosts" => "hottopic",
			"registration_method" => "regtype",
			"spamWaitTime" => "postfloodsecs",
			"reserveNames" => "bannedusernames",
			"avatar_max_height_upload" => "maxavatardims",
			"avatar_max_width_upload" => "maxavatardims",
			"failed_login_threshold" => "failedlogincount",
			"edit_disable_time" => "edittimelimit",
			"max_messageLength" => "maxmessagelength",
			"max_signatureLength" => "siglength",
			"defaultMaxTopics" => "threadsperpage",
			"defaultMaxMembers" => "membersperpage",
			"time_offset" => "timezoneoffset"
		);

	function import()
	{
		global $mybb, $output, $import_session, $db;

		$int_to_yes_no = array(
			"karmaMode" => 1,
			"enableCompressedOutput" => 1,
			"attachmentThumbnails" => 1,
			"cal_enabled" => 1
		);

		$int_to_on_off = array(
			"enableErrorLogging" => 1
		);

		$query = $this->old_db->simple_select("settings", "variable, value", "variable IN('".implode("','", array_keys($this->convert_settings))."')", array('limit_start' => $this->trackers['start_settings'], 'limit' => $import_session['settings_per_screen']));
		while($setting = $this->old_db->fetch_array($query))
		{
			// SMF values
			$name = $this->convert_settings[$setting['variable']];
			$value = $setting['value'];

			if($setting['variable'] == "karmaMode")
			{
				if($value == "2")
				{
					$value = 1;
				}
			}

			if($setting['variable'] == "mail_type")
			{
				if($value == 1)
				{
					$value = "smtp";
				}
				else
				{
					$value = "mail";
				}
			}

			if($setting['variable'] == "avatar_max_height_upload")
			{
				$avatar_setting = "x".$value;
				continue;
			}
			else if($setting['variable'] == "avatar_max_width_upload")
			{
				$value = $value.$avatar_setting;
				unset($avatar_setting);
			}

			if($setting['variable'] == "registration_method")
			{
				if($value == 0)
				{
					$value = "instant";
				}
				else if($value == 2)
				{
					$value = "admin";
				}
				else
				{
					$value = "verify";
				}
			}

			if($setting['variable'] == "reserveNames")
			{
				$value = str_replace("\n", ",", $value);
			}

			if(($value == 0 || $value == 1) && isset($int_to_yes_no[$setting['conf_name']]))
			{
				$value = int_to_yes_no($value, $int_to_yes_no[$setting['conf_name']]);
			}

			if(($value == 0 || $value == 1) && isset($int_to_on_off[$setting['variable']]))
			{
				$value = int_to_on_off($value, $int_to_on_off[$setting['variable']]);
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
			$query = $this->old_db->simple_select("settings", "COUNT(*) as count", "variable IN('".implode("','", array_keys($this->convert_settings))."')");
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