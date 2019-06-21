<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2019 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 *
 * Contact @yuliu in GitHub if you need assistance.
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class DZX25_Converter extends Converter
{
	
	/**
	 * String of the bulletin board name
	 *
	 * @var string
	 */
	var $bbname = "Discuz! X2.5";
	
	/**
	 * String of the plain bulletin board name
	 *
	 * @var string
	 */
	var $plain_bbname = "Discuz! X2.5";
	
	/**
	 * Whether or not this module requires the loginconvert.php plugin
	 *
	 * @var boolean
	 */
	var $requires_loginconvert = true;
	
	/**
	 * Array of all of the modules
	 *
	 * @var array
	 */
	var $modules = array(
			"db_configuration"			=> array("name" => "Database Configuration", "dependencies" => ""),
			"import_settings"			=> array("name" => "Settings", "dependencies" => "db_configuration"),
			"import_usergroups"			=> array("name" => "Usergroups", "dependencies" => "db_configuration"),
			"import_users"				=> array("name" => "Users", "dependencies" => "db_configuration,import_usergroups"),
			"import_forums"				=> array("name" => "Forums", "dependencies" => "db_configuration"),
			"import_forumperms"			=> array("name" => "Forum Permissions", "dependencies" => "db_configuration,import_forums"),
			"import_threadprefixes"		=> array("name" => "Thread Prefixes", "dependencies" => "db_configuration,import_forums,import_usergroups"),	// Customed converter module
			"import_threads"			=> array("name" => "Threads", "dependencies" => "db_configuration,import_forums,import_users,import_threadprefixes"),
			"import_polls"				=> array("name" => "Polls", "dependencies" => "db_configuration,import_threads"),
			"import_pollvotes"			=> array("name" => "Poll Votes", "dependencies" => "db_configuration,import_polls"),
			"import_posts"				=> array("name" => "Posts", "dependencies" => "db_configuration,import_threads"),
			"import_privatemessages"	=> array("name" => "Private Messages", "dependencies" => "db_configuration,import_users"),
			"import_moderators"			=> array("name" => "Moderators", "dependencies" => "db_configuration,import_forums,import_users"),
			"import_announcements"		=> array("name" => "Announcements", "dependencies" => "db_configuration,import_users"),	// Customed converter module
			"import_profilefields"		=> array("name" => "Extended User Profile Fields", "dependencies" => "db_configuration"),	// Customed converter module
			"import_userfields"			=> array("name" => "Extended User Profile Infos", "dependencies" => "db_configuration,import_users,import_profilefields"),	// Customed converter module
			"import_buddies"			=> array("name" => "Buddies", "dependencies" => "db_configuration,import_users"),	// Customed converter module
/*			"import_events"				=> array("name" => "Calendar Events", "dependencies" => "db_configuration,import_posts"),
*/			"import_avatars"			=> array("name" => "Avatars", "dependencies" => "db_configuration,import_users"),
			"import_attachments"		=> array("name" => "Attachments", "dependencies" => "db_configuration,import_posts"),
			
	);
	
	/**
	 * The table we check to verify it's "our" database
	 *
	 * @var String
	 */
	var $check_table = "common_member";
	
	/**
	 * The table prefix we suggest to use
	 *
	 * @var String
	 */
	var $prefix_suggestion = "dz_";
	
	/**
	 * An array of smf -> mybb groups
	 *
	 * @var array
	 */
	var $groups = array(
			1 => MYBB_ADMINS, // Administrators
			2 => MYBB_SMODS, // Super Moderators
			3 => MYBB_MODS, // Mods
			4 => MYBB_BANNED, // Banned from posting
			5 => MYBB_BANNED, // Banned from viewing forums
			6 => MYBB_BANNED, // Banned from visiting whole site
			7 => MYBB_GUESTS, // Guests
			8 => MYBB_AWAITING, // Awaiting Activation
			9 => MYBB_REGISTERED, // Registered
	);
	
	var $column_length_to_check = array(
			'[placeholder]old_table' => array(
					'[placeholder]our_table' => array(
							'[placeholder]old_column' => '[placeholder]new_column'
					)
			)
	);
	
	var $get_post_cache = array();
	
}


