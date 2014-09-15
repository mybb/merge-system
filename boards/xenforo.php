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

class XENFORO_Converter extends Converter
{

	/**
	 * String of the bulletin board name
	 *
	 * @var string
	 */
	var $bbname = "Xenforo 1";
	
	/**
	 * String of the plain bulletin board name
	 *
	 * @var string
	 */
	var $plain_bbname = "Xenforo 1";
	
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
						 "import_threads" => array("name" => "Threads", "dependencies" => "db_configuration,import_forums"),
						 "import_polls" => array("name" => "Polls", "dependencies" => "db_configuration,import_threads"),
						 "import_pollvotes" => array("name" => "Poll Votes", "dependencies" => "db_configuration,import_polls"),
						 "import_posts" => array("name" => "Posts", "dependencies" => "db_configuration,import_threads"),
						 "import_privatemessages" => array("name" => "Private Messages", "dependencies" => "db_configuration,import_users"),
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
	 * Convert a XF group ID into a MyBB group ID
	 *
	 * @param int Group ID
	 * @param array Options for retreiving the group ids
	 * @return mixed group id(s)
	 */
	function get_group_id($gid, $options=array())
	{
		$query = $this->old_db->simple_select("user_group", "*", "user_group_id='{$gid}'");
		if(!$query)
		{
			return MYBB_REGISTERED;
		}

		$groups = array();
		while($xfgroup = $this->old_db->fetch_array($query))
		{
			if($options['original'] == true)
			{
				$groups[] = $xfgroup['user_group_id'];
			}
			else
			{
				$groups[] = $this->get_gid($xfgroup['user_group_id']);
			}
		}

		$this->old_db->free_result($query);
		return implode(',', array_unique($groups));
	}
}

?>