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

define("DZX25_USE_MIN_PWLEN", 6);

class DZX25_Converter_Module_Settings extends Converter_Module_Settings {
	
	var $settings = array(
			'friendly_name' => 'settings',
			'default_per_screen' => 1000,
	);
	
	// What settings do we need to get and what is their MyBB equivalent?
	var $convert_settings = array(
			"closedreason" => "boardclosed_reason",
			"bbname" => "bbname",
			"sitename" => "homename",
			"siteurl" => "homeurl",
			"adminemail" => "adminemail",
			"forumjump" => "enableforumjump",
			"timeoffset" => "timezoneoffset",	// X => +X
			"subforumsindex" => "subforumsindex", 	// override mybb setting only if old bbs has value 0: 0 => 0
			"hideprivate" => "hideprivateforums",
			"whosonlinestatus" => "showwol",	// 0 => 0, otherwise => 1
			"topicperpage" => "threadsperpage",
			"postperpage" => "postsperpage",
			"regstatus" => "disableregs",	// 0 => 1
			"regverify" => "regtype", // 0 => 'instant"; 1 => 'verify'; 2 => 'admin'
			"pwlength" => "minnamelength",	// if no less than DZX25_MIN_PWLEN for security reason and no more than `maxpasswordlength`
			"regctrl" => "betweenregstime",
			"regfloodctrl" => "maxregsbetweentime",
			"minpostsize" => "minmessagelength",
			"maxpostsize" => "maxmessagelength",	//  TEXT:65535, MEDIUMTEXT:16777215, LONGTEXT:4294967295
			"maxpolloptions" => "maxpolloptions",
			"allowmoderatingthread" => "showownunapproved",
			"memliststatus" => "enablememberlist",	// TODO: not sure if it's OK to convert this field.
	);
	
	function import()
	{
		global $import_session;
		
		$int_to_yes_no = array(
				"forumjump" => 1,
				"hideprivate" => 1,
				"whosonlinestatus" => 1,	// needs converting to 0/1 integer
				"regstatus" => 0,	// needs converting to 0/1 integer
				"allowmoderatingthread" => 1,
				"memliststatus" => 1,
		);
		
		$int_to_on_off = array(
				"_PREFER_NO_SUCH_SETTING" => 1
		);
		
		// TODO: Avatar setting needs to be researched
		$avatar_setting = '';
		$query = $this->old_db->simple_select("common_setting", "skey, svalue", "skey IN('".implode("','", array_keys($this->convert_settings))."')", array('limit_start' => $this->trackers['start_settings'], 'limit' => $import_session['settings_per_screen']));
		while($setting = $this->old_db->fetch_array($query))
		{
			// Whether we need a real database update.
			$update = true;
			
			// Discuz! X2.5 values
			$name = $this->convert_settings[$setting['skey']];
			$value = $setting['svalue'];
			
			if($setting['skey'] == "timeoffset")
			{
				$value = $value > 0 ? "+$value" : $value;
			}
			
			if($setting['skey'] == "subforumsindex")
			{
				if($value > 0)
				{
					$update = false;
				}
			}
			
			if($setting['skey'] == "whosonlinestatus")
			{
				if($value > 0)
				{
					$value = 1;
				}
			}
			
			if($setting['skey'] == "regstatus")
			{
				if($value > 0 && $value < 3)
				{
					$value = 1;
				}
			}
			
			if($setting['skey'] == "regverify")
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
			
			if($setting['skey'] == "pwlength")
			{
				if(defined("DZX25_USE_MIN_PWLEN") && $value < DZX25_USE_MIN_PWLEN)
				{
					$value = 6;
				}
				else
				{
				}
			}
			
			if(($value == 0 || $value == 1) && isset($int_to_yes_no[$setting['skey']]))
			{
				$value = int_to_yes_no($value, $int_to_yes_no[$setting['skey']]);
			}
			
			if(($value == 0 || $value == 1) && isset($int_to_on_off[$setting['skey']]))
			{
				$value = int_to_on_off($value, $int_to_on_off[$setting['skey']]);
			}
			
			// Encode data to use UTF8, important fields including `closedreason`, `bbname`, `sitename`.
			$value = $this->board->encode_to_utf8($value, "common_setting", "settings");
			
			$this->dz_update_setting($name, $value, $update);
		}
	}
	
	function fetch_total()
	{
		global $import_session;
		
		// Get number of settings
		if(!isset($import_session['total_settings']))
		{
			$query = $this->old_db->simple_select("common_setting", "COUNT(*) as count", "skey IN('".implode("','", array_keys($this->convert_settings))."')");
			$import_session['total_settings'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}
		
		return $import_session['total_settings'];
	}
	
	/**
	 * Discuz! converter customed: update setting in the database, if it needs updating.
	 *
	 * @param string $name The name of the setting being inserted
	 * @param string $value The value of the setting being inserted
	 * $param bool $update true for updating, default.
	 */
	public function dz_update_setting($name, $value, $update = true)
	{
		global $db, $output, $lang;
		
		$this->debug->log->trace0("Updating setting {$name}" . ($update ? '' : ' not needed'));
		
		$output->print_progress("start", $lang->sprintf($lang->module_settings_updating, htmlspecialchars_uni($name)));
		
		$modify = array(
				'value' => $db->escape_string($value)
		);
		
		$this->debug->log->datatrace('$value', $value);
		
		if($update)
		{
			$db->update_query("settings", $modify, "name='{$name}'");
		}
		
		$this->increment_tracker('settings');
		
		$output->print_progress("end");
	}
}


