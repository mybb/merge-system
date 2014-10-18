<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2011 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class VBULLETIN3_Converter_Module_Settings extends Converter_Module_Settings {

	var $settings = array(
		'friendly_name' => 'settings',
		'default_per_screen' => 1000,
	);

	// What settings do we need to get and what is their MyBB equivalent?
	var $convert_settings = array(
			"addtemplatename" => "tplhtmlcomments",
			"allowregistration" => "disableregs",
			"allowkeepbannedemail" => "emailkeep",
			"attachlimit" => "maxattachments",
			"attachthumbs" => "attachthumbnails",
			"attachthumbssize" => "attachthumbh",
			"bbactive" => "boardclosed",
			"bbclosedreason" => "boardclosed_reason",
			"dateformat" => "dateformat",
			"displayloggedin" => "showwol",
			"dstonoff" => "dstcorrection",
			"edittimelimit" => "edittimelimit",
			"enablememberlist" => "enablememberlist",
			"enablepms" => "enablepms",
			"floodchecktime" => "postfloodsecs",
			"forumhomedepth" => "subforumsindex",
			"gziplevel" => "gziplevel",
			"gzipoutput" => "gzipoutput",
			"hotnumberposts" => "hottopic",
			"hotnumberviews" => "hottopicviews",
			"illegalusernames" => "bannedusername",
			"loadlimit" => "load",
			"logip" => "logip",
			"maximages" => "maxpostimages",
			"maxpolllength" => "polloptionlimit",
			"maxpolloptions" => "maxpolloptions",
			"maxposts" => "postsperpage",
			"maxthreads" => "threadsperpage",
			"maxuserlength" => "maxnamelength",
			"memberlistperpage" => "membersperpage",
			"minsearchlength" => "minsearchword",
			"minuserlength" => "minnamelength",
			"moderatenewmembers" => "regtype",
			"nocacheheaders" => "nocacheheaders",
			"postmaxchars" => "maxmessagelength",
			"postminchars" => "minmessagelength",
			"privallowbbcode" => "pmsallowmycode",
			"privallowbbimagecode" => "pmsallowimgcode",
			"privallowhtml" => "pmsallowhtml",
			"privallowsmilies" => "pmsallowsmilies",
			"registereddateformat" => "regdateformat",
			"reputationenable" => "enablereputation",
			"searchfloodtime" => "searchfloodtime",
			"showbirthdays" => "showbirthdays",
			"showdots" => "dotfolders",
			"showforumdescription" => "showdescriptions",
			"showprivateforums" => "hideprivateforums",
			"showsimilarthreads" => "showsimilarthreads",
			"smtp_host" => "smtp_host",
			"smtp_pass" => "smtp_pass",
			"smtp_port" => "smtp_port",
			"smtp_user" => "smtp_user",
			"timeformat" => "timeformat",
			"timeoffset" => "timezoneoffset",
			"useheaderredirect" => "redirects",
			"usereferrer" => "usereferrals",
			"usermaxposts" => "userpppoptions",
			"WOLrefresh" => "refreshwol"
		);

	function import()
	{
		global $import_session;

		$settings = "";
		$int_to_yes_no = array(
			"addtemplatename" => 1,
			"allowregistration" => 0,
			"allowkeepbannedemail" => 1,
			"attachthumbs" => 1,
			"bbactive" => 0,
			"displayloggedin" => 1,
			"dstonoff" => 1,
			"enablememberlist" => 1,
			"enablepms" => 1,
			"gzipoutput" => 1,
			"nocacheheaders" => 1,
			"privallowbbcode" => 1,
			"privallowbbimagecode" => 1,
			"privallowhtml" => 1,
			"privallowsmilies" => 1,
			"reputationenable" => 1,
			"showbirthdays" => 1,
			"showdots" => 1,
			"showforumdescription" => 1,
			"showsimilarthreads" => 1,
			"usereferrer" => 1
		);

		$query = $this->old_db->simple_select("setting", "varname, value", "varname IN('".implode("','", array_keys($this->convert_settings))."')", array('limit_start' => $this->trackers['start_settings'], 'limit' => $import_session['settings_per_screen']));
		while($setting = $this->old_db->fetch_array($query))
		{
			// vBulletin 3.6 values
			$name = $this->convert_settings[$setting['varname']];
			$value = $setting['value'];

			if($setting['varname'] == "logip")
			{
				if($value == 1)
				{
					$value = "hide";
				}
				else if($value == 2)
				{
					$value = "show";
				}
				else
				{
					$value = 0;
				}
			}

			if($setting['varname'] == "moderatenewmembers")
			{
				if($setting['config_value'] == 1)
				{
					$value = "admin";
				}
				else
				{
					$value = "verify";
				}
			}

			if($setting['varname'] == "WOLrefresh")
			{
				$value = ceil($value / 60);
			}

			if($setting['varname'] == "showforumusers")
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

			if($setting['varname'] == "useheaderredirect")
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

			if($setting['varname'] == "showprivateforums")
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

			if(($value == 0 || $value == 1) && isset($int_to_yes_no[$setting['varname']]))
			{
				$value = int_to_yes_no($value, $int_to_yes_no[$setting['varname']]);
			}

			$this->update_setting($name, $value);

			if($setting['varname'] == "attachthumbssize")
			{
				$name = "attachthumbw";
				$this->update_setting($name, $value);
			}
		}
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of settings
		if(!isset($import_session['total_settings']))
		{
			$query = $this->old_db->simple_select("setting", "COUNT(*) as count", "varname IN('".implode("','", array_keys($this->convert_settings))."')");
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