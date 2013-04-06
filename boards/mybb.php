<?php
/**
 * MyBB 1.6
 * Copyright © 2009 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
  * License: http://www.mybb.com/about/license
 *
 * $Id: mybb.php 4394 2010-12-14 14:38:21Z ralgith $
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class MYBB_Converter extends Converter
{
	/**
	 * String of the bulletin board name
	 *
	 * @var string
	 */
	var $bbname = "MyBB 1.6 (Merge)";
	
	/**
	 * String of the plain bulletin board name
	 *
	 * @var string
	 */
	var $plain_bbname = "MyBB 1.6";
	
	/**
	 * Whether or not this module requires the loginconvert.php plugin
	 *
	 * @var boolean
	 */
	var $requires_loginconvert = false;
	
	/**
	 * Array of all of the modules
	 *
	 * @var array
	 */
	var $modules = array("db_configuration" => array("name" => "Database Configuration", "dependencies" => ""),
						 "import_usergroups" => array("name" => "Usergroups", "dependencies" => "db_configuration"),
						 "import_users" => array("name" => "Users", "dependencies" => "db_configuration,import_usergroups"),
						 "import_forums" => array("name" => "Forums", "dependencies" => "db_configuration,import_users"),
						 "import_threads" => array("name" => "Threads", "dependencies" => "db_configuration,import_forums"),
						 "import_polls" => array("name" => "Polls", "dependencies" => "db_configuration,import_threads"),
						 "import_pollvotes" => array("name" => "Poll Votes", "dependencies" => "db_configuration,import_polls"),
						 "import_posts" => array("name" => "Posts", "dependencies" => "db_configuration,import_threads"),
						 "import_moderators" => array("name" => "Moderators", "dependencies" => "db_configuration,import_forums,import_users"),
						 "import_privatemessages" => array("name" => "Private Messages", "dependencies" => "db_configuration,import_users"),
						 "import_settings" => array("name" => "Settings", "dependencies" => "db_configuration"),
						 "import_events" => array("name" => "Calendar Events", "dependencies" => "db_configuration,import_users"),
						 "import_attachments" => array("name" => "Attachments", "dependencies" => "db_configuration,import_posts"),
						);
	
	/**
	 * Convert a MyBB group ID into a MyBB group ID (merge)
	 *
	 * @param int Group ID
	 * @param array Options for retreiving the group ids
	 * @return mixed group id(s)
	 */
	function get_group_id($gid, $options=array())
	{
		$settings = array();
		if($options['not_multiple'] == false)
		{
			$query = $this->old_db->simple_select("usergroups", "COUNT(*) as rows", "gid='{$gid}'");
			$settings = array('limit_start' => '1', 'limit' => $this->old_db->fetch_field($query, 'rows'));
			$this->old_db->free_result($query);
		}
		
		$query = $this->old_db->simple_select("usergroups", "*", "gid='{$gid}'", $settings);
		
		$comma = $group = '';
		while($mybbgroup = $this->old_db->fetch_array($query))
		{
			if($options['original'] == true)
			{
				$group .= $mybbgroup['gid'].$comma;
			}
			else
			{
				$group .= $comma;
				switch($mybbgroup['gid'])
				{
					case 5: // Awaiting activation
						$group .= 5;
						break;
					case 1: // Guests
						$group .= 1;
					case 2: // Registered
						$group .= 2;
						break;
					case 7: // Banned
						$group .= 7;
						break;
					case 4: // Administrator
						$group .= 4;
						break;
					default:
						$gid = $this->get_import->gid($mybbgroup['gid']);
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