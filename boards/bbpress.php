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

class BBPRESS_Converter extends Converter
{

	/**
	 * String of the bulletin board name
	 *
	 * @var string
	 */
	var $bbname = "BBPress 2.5";

	/**
	 * String of the plain bulletin board name
	 *
	 * @var string
	 */
	var $plain_bbname = "BBPress 2.5";

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
						 "import_forums" => array("name" => "Forums", "dependencies" => "db_configuration,import_users"),
						 "import_threads" => array("name" => "Threads", "dependencies" => "db_configuration,import_forums"),
						 "import_posts" => array("name" => "Posts", "dependencies" => "db_configuration,import_threads"),
						);

	/**
	 * The table we check to verify it's "our" database
	 * 
	 * @var String
	 */
	var $check_table = "usermeta";

	/**
	 * The table prefix we suggest to use
	 *
	 * @var String
	 */
	var $prefix_suggestion = "wp_";
	
	/**
	 * An array of bbpress -> mybb groups
	 * bbpress doesn't use id's but names
	 *
	 * @var array
	 */
	var $groups = array(
		"blocked" => MYBB_BANNED, // Banned
		"member" => MYBB_REGISTERED, // Registered
		"moderator" => MYBB_SMODS, // Super Moderators
		"keymaster" => MYBB_ADMINS, // Administrators
		"administrator" => MYBB_REGISTERED, // Administrators
	);

	/**
	 * What BBCode Parser we're using
	 *
	 * @var String
	 */
	var $parser_class = "html";

	/**
	 * Convert a bbPress group ID into a MyBB group ID
	 *
	 * @param int Group ID
	 * @param array Options for retreiving the group ids
	 * @return mixed group id(s)
	 */
	function get_group_id($uid, $options=array())
	{
		global $old_table_prefix;
		$settings = array();
		if($options['not_multiple'] == false)
		{
			$query = $this->old_db->simple_select("usermeta", "COUNT(*) as rows", "user_id = '{$uid}' AND meta_key = '".$this->old_db->table_prefix."capabilities'");
			$settings = array('limit_start' => '1', 'limit' => $this->old_db->fetch_field($query, 'rows'));
			$this->old_db->free_result($query);
		}

		$query = $this->old_db->simple_select("usermeta", "*", "user_id = '{$uid}' AND meta_key = '".$this->old_db->table_prefix."capabilities'", $settings);
		if(!$query)
		{
			return MYBB_REGISTERED;
		}

		$groups = array();
		while($bbpress = $this->old_db->fetch_array($query))
		{
			$bbpress['group_id'] = preg_replace('#\w+:\d+:{\w+:\d+:\"(.*?)\";\w+:\d+;}#', '$1', $bbpress['meta_value']);

			$groups[] = $this->get_gid($bbpress['group_id']);
		}

		$this->old_db->free_result($query);
		return implode(',', array_unique($groups));
	}
}

?>