<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 *
 * $Id$
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class MINGLE_Converter extends Converter
{

	/**
	 * String of the bulletin board name
	 *
	 * @var string
	 */
	var $bbname = "Mingle Forum 1.0";

	/**
	 * String of the plain bulletin board name
	 *
	 * @var string
	 */
	var $plain_bbname = "Mingle Forum 1.0";

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
						 "import_usertitles" => array("name" => "Usertitles", "dependencies" => "db_configuration"),
						 "import_usergroups" => array("name" => "Usergroups", "dependencies" => "db_configuration,import_users"),
						 "import_categories" => array("name" => "Categories", "dependencies" => "db_configuration,import_usergroups"),
						 "import_forums" => array("name" => "Forums", "dependencies" => "db_configuration,import_categories"),
						 "import_moderators" => array("name" => "Moderators", "dependencies" => "db_configuration,import_forums,import_users"),
						 "import_threads" => array("name" => "Threads", "dependencies" => "db_configuration,import_forums"),
						 "import_posts" => array("name" => "Posts", "dependencies" => "db_configuration,import_threads"),
						);

	/**
	 * Convert a Mingle group ID into a MyBB group ID (really only for admins)
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
			$query = $this->old_db->simple_select("usermeta", "COUNT(*) as rows", "user_id = '{$uid}' AND meta_key = 'wpf_capabilities'");
			$settings = array('limit_start' => '1', 'limit' => $this->old_db->fetch_field($query, 'rows'));
			$this->old_db->free_result($query);
		}

		$query = $this->old_db->simple_select("usermeta", "*", "user_id = '{$uid}' AND meta_key = '".$this->old_db->table_prefix."capabilities'", $settings);

		while($mingle = $this->old_db->fetch_array($query))
		{
			$meta_options = unserialize($mingle['meta_value']);
			if(array_key_exists("administrator", $meta_options))
			{
				$group = 4;
			}
			else
			{
				$group = 2;
			}
		}
		if(!$query)
		{
			return 2; // Return regular registered user.
		}

		$this->old_db->free_result($query);
		return $group;
	}


}

?>
