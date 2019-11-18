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

class XENFORO2_Converter extends Converter
{
	/**
	 * String of the bulletin board name
	 *
	 * @var string
	 */
	var $bbname = "Xenforo 2";

	/**
	 * String of the plain bulletin board name
	 *
	 * @var string
	 */
	var $plain_bbname = "Xenforo 2";

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
		"db_configuration" => array("name" => "Database Configuration", "dependencies" => ""),
		"import_settings" => array("name" => "Settings", "dependencies" => "db_configuration"),
		"import_usergroups" => array("name" => "Usergroups", "dependencies" => "db_configuration"),
		"import_users" => array("name" => "Users", "dependencies" => "db_configuration,import_usergroups"),
		"import_forums" => array("name" => "Forums", "dependencies" => "db_configuration,import_users"),
		"import_moderators" => array("name" => "Moderators", "dependencies" => "db_configuration,import_forums,import_users"),
		"import_threads" => array("name" => "Threads", "dependencies" => "db_configuration,import_forums"),
		"import_polls" => array("name" => "Polls", "dependencies" => "db_configuration,import_threads"),
		"import_pollvotes" => array("name" => "Poll Votes", "dependencies" => "db_configuration,import_polls"),
		"import_posts" => array("name" => "Posts", "dependencies" => "db_configuration,import_threads"),
		"import_privatemessages" => array("name" => "Private Messages", "dependencies" => "db_configuration,import_users"),
		"import_avatars" => array("name" => "Avatars", "dependencies" => "db_configuration,import_users"),
		"import_attachments" => array("name" => "Attachments", "dependencies" => "db_configuration,import_posts"),
	);

	/**
	 * The table we check to verify it's "our" database
	 *
	 * @var String
	 */
	var $check_table = "ip";

	/**
	 * The table prefix we suggest to use
	 *
	 * @var String
	 */
	var $prefix_suggestion = "xf_";

	/**
	 * An array of xenforo -> mybb groups
	 *
	 * @var array
	 */
	var $groups = array(
		1 => MYBB_GUESTS, // Guests
		2 => MYBB_REGISTERED, // Registered
		3 => MYBB_ADMINS, // Administrators
		4 => MYBB_MODS, // Moderators
	);

	/**
	 * An array of supported databases
	 * XenForo only supports MySQL
	 */
	var $supported_databases = array("mysql");

	var $column_length_to_check = array(
		"user_group" => array(
			"usergroups" => array(
				"title" => "title",
				"username_css" => "namestyle",
			),
		),
		"user" => array(
			"users" => array(
				"username" => "username",
				"email" => "email",
			),
		),
		"user_profile" => array(
			"users" => array(
				"website" => "website",
			),
		),
		"thread" => array(
			"threads" => array(
				"title" => "subject",
			),
		),
		"post" => array(
			"posts" => array(
				"message" => "message",
			),
		),
	);

	/**
	 * Get imported thread and cache it during script processing.
	 */
	var $cache_threads = array();
	function get_thread($tid)
	{
		global $db;
		
		if(isset($this->cache_threads[$tid]))
		{
			return $this->cache_threads[$tid];
		}
		
		$query = $db->simple_select("threads", "fid,subject,dateline,visible", "tid='{$tid}'", array("limit" => 1));
		$thread = $db->fetch_array($query);
		$db->free_result($query);
		
		$this->cache_threads[$tid] = $thread;
		return $this->cache_threads[$tid];
	}
}

