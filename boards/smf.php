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

class SMF_Converter extends Converter
{

	/**
	 * String of the bulletin board name
	 *
	 * @var string
	 */
	var $bbname = "SMF 1.1";

	/**
	 * String of the plain bulletin board name
	 *
	 * @var string
	 */
	var $plain_bbname = "SMF 1";

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
	var $modules = array("db_configuration" => array("name" => "Database Configuration", "dependencies" => ""),
						 "import_usergroups" => array("name" => "Usergroups", "dependencies" => "db_configuration"),
						 "import_users" => array("name" => "Users", "dependencies" => "db_configuration,import_usergroups"),
						 "import_categories" => array("name" => "Categories", "dependencies" => "db_configuration,import_users"),
						 "import_forums" => array("name" => "Forums", "dependencies" => "db_configuration,import_categories"),
						 "import_forumperms" => array("name" => "Forum Permissions", "dependencies" => "db_configuration,import_forums"),
						 "import_threads" => array("name" => "Threads", "dependencies" => "db_configuration,import_forums"),
						 "import_polls" => array("name" => "Polls", "dependencies" => "db_configuration,import_threads"),
						 "import_pollvotes" => array("name" => "Poll Votes", "dependencies" => "db_configuration,import_polls"),
						 "import_posts" => array("name" => "Posts", "dependencies" => "db_configuration,import_threads"),
						 "import_privatemessages" => array("name" => "Private Messages", "dependencies" => "db_configuration,import_users"),
						 "import_moderators" => array("name" => "Moderators", "dependencies" => "db_configuration,import_forums,import_users"),
						 "import_settings" => array("name" => "Settings", "dependencies" => "db_configuration"),
						 "import_events" => array("name" => "Calendar Events", "dependencies" => "db_configuration,import_posts"),
						 "import_attachments" => array("name" => "Attachments", "dependencies" => "db_configuration,import_posts"),
						);

	/**
	 * The table we check to verify it's "our" database
	 *
	 * @var String
	 */
	var $check_table = "boards";

	/**
	 * The table prefix we suggest to use
	 *
	 * @var String
	 */
	var $prefix_suggestion = "smf_";

	/**
	 * An array of smf -> mybb groups
	 *
	 * @var array
	 */
	var $groups = array(
		1 => MYBB_ADMINS, // Administrators
		2 => MYBB_SMODS, // Super Moderators
		3 => MYBB_MODS, // Moderators
		// 0 => MYBB_REGISTERED, // Registered
	);

	var $get_post_cache = array();

	/**
	 * Get a post from the SMF database
	 *
	 * @param int Post ID
	 * @return array The post
	 */
	function get_post($pid)
	{
		if(array_key_exists($pid, $this->get_post_cache))
		{
			return $this->get_post_cache[$pid];
		}

		$pid = intval($pid);

		$query = $this->old_db->simple_select("messages", "*", "ID_MSG = '{$pid}'", array('limit' => 1));
		$results = $this->old_db->fetch_array($query);
		$this->old_db->free_result($query);

		$this->get_post_cache[$pid] = $results;

		return $results;
	}

	/**
	 * Convert a SMF group ID into a MyBB group ID
	 *
	 * @param int Group ID
	 * @param boolean whether or not the Group ID came from ID_GROUP column
	 * @return mixed group id(s)
	 */
	function get_group_id($group_id, $is_group_row=false, $is_activated=1)
	{
		if(empty($group_id))
		{
			return MYBB_REGISTERED;
		}

		if(!is_numeric($group_id))
		{
			$groups = $group_id;
		}
		else
		{
			$groups = array($group_id);
		}


		$ngroups = array();
		foreach($groups as $key => $smfgroup)
		{
			// Deal with non-activated people
			if($is_activated != 1 && $is_group_row == true)
			{
				return MYBB_AWAITING;
			}

			$ngroups[] = $this->get_gid($punbbgroup['g_id']);
		}

		return implode(',', array_unique($ngroups));
	}
}

?>