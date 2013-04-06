<?php
/**
 * MyBB 1.6
 * Copyright © 2009 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
  * License: http://www.mybb.com/about/license
 *
 * $Id: phpbb3.php 4394 2010-12-14 14:38:21Z ralgith $
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

		$comma = $group = '';
		while($phpbbgroup = $this->old_db->fetch_array($query))
		{
			if($options['original'] == true)
			{
				$group .= $phpbbgroup['group_id'].$comma;
			}
			else
			{
				// Deal with non-activated people
				if($phpbbgroup['user_pending'] != '0')
				{
					return 5;
				}

				$group .= $comma;
				switch($phpbbgroup['group_id'])
				{
					case 1: // Guests
					case 6: // Bots
						$group .= 1;
						break;
					case 2: // Register
					case 3: // Registered coppa
						$group .= 2;
						break;
					case 4: // Super Moderator
						$group .= 3;
						break;
					case 5: // Administrator
						$group .= 4;
						break;
					default:
						$gid = $this->get_import->gid($phpbbgroup['group_id']);
						if($gid > 0)
						{
							// If there is an associated custom group...
							$group .= $gid;
						}
						else
						{
							// The lot
							$group .= 2;
						}					
				}			
			}
			$comma = ',';
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