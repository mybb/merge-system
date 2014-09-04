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

class PHPBB3_Converter extends Converter
{

	/**
	 * String of the bulletin board name
	 *
	 * @var string
	 */
	var $bbname = "phpBB 3";

	/**
	 * String of the plain bulletin board name
	 *
	 * @var string
	 */
	var $plain_bbname = "phpBB 3";

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
						 "import_users" => array("name" => "Users", "dependencies" => "db_configuration"),
						 "import_usergroups" => array("name" => "Usergroups", "dependencies" => "db_configuration,import_users"),
						 "import_forums" => array("name" => "Forums", "dependencies" => "db_configuration,import_users"),
						 "import_forumperms" => array("name" => "Forum Permissions", "dependencies" => "db_configuration,import_forums,import_usergroups"),
						 "import_threads" => array("name" => "Threads", "dependencies" => "db_configuration,import_forums"),
						 "import_polls" => array("name" => "Polls", "dependencies" => "db_configuration,import_threads"),
						 "import_pollvotes" => array("name" => "Poll Votes", "dependencies" => "db_configuration,import_polls"),
						 "import_posts" => array("name" => "Posts", "dependencies" => "db_configuration,import_threads"),
						 "import_privatemessages" => array("name" => "Private Messages", "dependencies" => "db_configuration,import_users"),
						 "import_moderators" => array("name" => "Moderators", "dependencies" => "db_configuration,import_forums,import_users"),
						 "import_settings" => array("name" => "Settings", "dependencies" => "db_configuration"),
						 "import_attachments" => array("name" => "Attachments", "dependencies" => "db_configuration,import_posts"),
						);

	/**
	 * The table we check to verify it's "our" database
	 *
	 * @var String
	 */
	var $check_table = "user_group";

	/**
	 * The table prefix we suggest to use
	 *
	 * @var String
	 */
	var $prefix_suggestion = "phpbb_";
	
	/**
	 * An array of phpbb -> mybb groups
	 * 
	 * @var array
	 */
	var $groups = array(
		1 => MYBB_GUESTS, // Guests
		2 => MYBB_REGISTERED, // Registered
		3 => MYBB_REGISTERED, // Newly registered
		4 => MYBB_SMODS, // Super Moderators
		5 => MYBB_ADMINS, // Administrators
		6 => MYBB_GUESTS, // Bots
		7 => MYBB_REGISTERED, // Newly registered
	);

	/**
	 * Convert a phpBB 3 group ID into a MyBB group ID
	 *
	 * @param int Group ID
	 * @param array Options for retreiving the group ids
	 * @return mixed group id(s)
	 */
	function get_group_id($uid, $options=array())
	{
		$settings = array();
		if($options['not_multiple'] == false)
		{
			$query = $this->old_db->simple_select("user_group", "COUNT(*) as rows", "user_id = '{$uid}'");
			$settings = array('limit_start' => '1', 'limit' => $this->old_db->fetch_field($query, 'rows'));
			$this->old_db->free_result($query);
		}

		$query = $this->old_db->simple_select("user_group", "*", "user_id = '{$uid}'", $settings);
		if(!$query)
		{
			return MYBB_REGISTERED;
		}

		$groups = array();
		while($phpbbgroup = $this->old_db->fetch_array($query))
		{
			if($options['original'] == true)
			{
				$groups[] = $phpbbgroup['group_id'];
			}
			else
			{
				// Deal with non-activated people
				if($phpbbgroup['user_pending'] != '0')
				{
					return MYBB_AWAITING;
				}

				$groups[] = $this->get_gid($phpbbgroup['group_id']);
			}
		}

		$this->old_db->free_result($query);
		return implode(',', array_unique($groups));
	}
}

?>