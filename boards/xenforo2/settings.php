<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2019 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class XENFORO2_Converter_Module_Settings extends Converter_Module_Settings
{
	var $settings = array(
		'friendly_name' => 'settings',
		'default_per_screen' => 1000,
	);

	// What settings do we need to get and what is their MyBB equivalent?
	var $convert_settings = array(
			/* Options - Board active */
			"boardActive" => "boardclosed",	// Inverted
			"boardInactiveMessage" => "boardclosed_reason",

			/* Options - Basic board information */
			"boardTitle" => "bbname",
			"defaultEmailAddress" => "adminemail",
			"contactEmailAddress" => "contactemail",
			"homePageUrl" => "homeurl",
			"guestTimeZone" => "timezoneoffset",	// City => GMT +/-X

			/* Options - Performance */
			"floodCheckLength" => "pmfloodsecs",	// Seconds. xf's is for messaging, including posting/pm/etc..
			"floodCheckLengthDiscussion" => "postfloodsecs",	// Seconds

			/* Options - Email options */
			"emailTransport" => array(
				"emailTransport" => "mail_handler",
				"smtpHost" => "smtp_host",
				"smtpPort" => "smtp_port",
				// "smtpAuth": login
				"smtpLoginUsername" => "smtp_user",
				"smtpLoginPassword" => "smtp_pass",
				"smtpEncrypt" => "secure_smtp",
			),

			/* Options - User registration */
			"registrationSetup" => array(
				"enabled" => "disableregs",	// Inverted
				"emailConfirmation" => "regtype",
				"moderation" => "regtype",
			),
			"usernameLength" => array(
				"min" => "minnamelength",
				"max" => "maxnamelength",
			),
			"registrationTimer" => "regtime",	// Seconds
			
			/* Options - User options */
			"enableMemberList" => "enablememberlist",
			"membersPerPage" => "membersperpage",
			"onlineStatusTimeout" => "wolcutoffmins",	// Minutes
			
			/* Options - Threads, discussions and conversations */
			"discussionsPerPage" => "threadsperpage",
			"pollMaximumResponses" => "polloptionlimit",
			"messageMaxLength" => "maxmessagelength",
			"messagesPerPage" => "postsperpage",
			"messageMaxImages" => "maxpostimages",
			"messageMaxMedia" => "maxpostvideos",

			/* Options - Attachments */
			//"attachmentMaxFileSize" => "",	// KB
			"attachmentMaxPerMessage" => "maxattachments",
			//"attachmentExtensions" => "",	// By line
			"attachmentThumbnailDimensions" => "attachthumbw|attachthumbh",
		);

	function import()
	{
		global $import_session;

		// TODO: utf8 conversion? Should be checked but xf 2 only uses utf8mb4. Other board converters can check this.
		$utf8_encode_field = array(
			"boardInactiveMessage",
			"boardTitle",
			"homePageUrl",
		);

		// Use MyBB's option name.
		$int_to_yes_no = array(
			"boardclosed" => 0,
			"disableregs" => 0,
			"enablememberlist" => 1,
		);

		// xf's options that may be imported.
		$query = $this->old_db->simple_select("option", "option_id, option_value, data_type", "option_id IN('".implode("','", array_keys($this->convert_settings))."')", array('limit_start' => $this->trackers['start_settings'], 'limit' => $import_session['settings_per_screen']));
		while($option = $this->old_db->fetch_array($query))
		{
			$xf_option_name = $option['option_id'];
			$xf_option_value = $option['option_value'];

			if($option['data_type'] == "array" && isset($this->convert_settings[$xf_option_name]) && is_array($this->convert_settings[$xf_option_name]))
			{
				$xf_option_value = json_decode($xf_option_value, true);
				if($xf_option_name == "registrationSetup")
				{
					global $mybb;
					$mybb_regtype = $mybb->settings['regtype'];
				}
				foreach($this->convert_settings[$xf_option_name] as $xf_sub_option_name => $mybb_option_name)
				{
					if(!isset($xf_option_value[$xf_sub_option_name]))
					{
						continue;
					}
					$mybb_option_value = $xf_option_value[$xf_sub_option_name];
					if(is_array($mybb_option_value))
					{
						continue;
					}

					if($xf_option_name == "emailTransport")
					{
						if($xf_sub_option_name == "emailTransport")
						{
							if($mybb_option_value == "smtp")
							{
								$mybb_option_value = "smtp";
							}
							else if($mybb_option_value == "sendmail")
							{
								$mybb_option_value = "mail";
							}
							// For compatibility.
							else
							{
								$mybb_option_value = "mail";
							}
						}

						if($xf_sub_option_name == "smtpEncrypt")
						{
							if($mybb_option_value == "tls")
							{
								$mybb_option_value = 2;
							}
							else if($mybb_option_value == "ssl")
							{
								$mybb_option_value = 1;
							}
							else
							{
								$mybb_option_value = 0;
							}
						}
					}
					if($xf_option_name == "registrationSetup")
					{
						if($xf_sub_option_name == "emailConfirmation")
						{
							if($mybb_option_value)
							{
								if($mybb_regtype == "admin")
								{
									$mybb_regtype = "both";
								}
								else if($mybb_regtype == "instant")
								{
									$mybb_regtype = "verify";
								}
							}
							$mybb_option_value = $mybb_regtype;
						}
						if($xf_sub_option_name == "moderation")
						{
							if($mybb_option_value)
							{
								if($mybb_regtype == "verify")
								{
									$mybb_regtype = "both";
								}
								else if($mybb_regtype == "instant")
								{
									$mybb_regtype = "admin";
								}
							}
							$mybb_option_value = $mybb_regtype;
						}
					}
					if(($mybb_option_value == 0 || $mybb_option_value == 1) && isset($int_to_yes_no[$mybb_option_name]))
					{
						$mybb_option_value = int_to_yes_no($mybb_option_value, $int_to_yes_no[$mybb_option_name]);
					}
					$this->update_setting($mybb_option_name, $mybb_option_value);
				}
			}
			else if(isset($this->convert_settings[$xf_option_name]))
			{
				$mybb_option_name = $this->convert_settings[$xf_option_name];
				$mybb_option_value = $xf_option_value;

				if($xf_option_name == "guestTimeZone")
				{
					$mybb_option_value = get_timezone($mybb_option_value);
				}

				if($xf_option_name == "attachmentThumbnailDimensions")
				{
					$mybb_option_name = explode("|", $mybb_option_name);
					$this->update_setting($mybb_option_name[0], $mybb_option_value);
					$mybb_option_name = $mybb_option_name[1];
				}

				if(($mybb_option_value == 0 || $mybb_option_value == 1) && isset($int_to_yes_no[$mybb_option_name]))
				{
					$mybb_option_value = int_to_yes_no($mybb_option_value, $int_to_yes_no[$mybb_option_name]);
				}

				if(in_array($xf_option_name, $utf8_encode_field))
				{
					$mybb_option_value = encode_to_utf8($mybb_option_value, "option", "settings");
				}
				$this->update_setting($mybb_option_name, $mybb_option_value);
			}
			$this->increment_tracker('settings');
		}
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of settings.
		if(!isset($import_session['total_settings']))
		{
			$query = $this->old_db->simple_select("option", "COUNT(*) as count", "option_id IN('".implode("','", array_keys($this->convert_settings))."')");
			$import_session['total_settings'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_settings'];
	}

	public function update_setting($name, $value)
	{
		global $db, $output, $lang;

		$this->debug->log->trace0("Updating setting {$name}");
		$output->print_progress("start", $lang->sprintf($lang->module_settings_updating, htmlspecialchars_uni($name)));

		$modify = array(
			'value' => $db->escape_string($value)
		);
		$this->debug->log->datatrace('$value', $value);
		$db->update_query("settings", $modify, "name='{$name}'");
		// Increase the tracker manually.
		//$this->increment_tracker('settings');
		$output->print_progress("end");
	}
}


