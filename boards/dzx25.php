<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2019 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 *
 * Refer to the wiki in GitHub if you need assistance:
 * https://github.com/yuliu/mybb-merge-system/wiki
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

/*************************************
 *********** Configuration ***********
 *************************************/
// Convert thread class from Discuz! to thread prefixes in MyBB without setting any permission on forum using and group using.
// If its value is true, the converter will require dependencies of the import of forums and usergroups. Otherwise, no dependency is required.
define("DZX25_CONVERTER_THREADCLASS_DEPS", true);
//define("DZX25_CONVERTER_USERS_LASTTIME", 1390492800);
// Overwrite some user data when importing more than one Discuz!.
// If set to false, user profiles will contain data mostly from the first converted Discuz!. User's last status, such as lastvisit, lastactivity, etc., will still be overwriten with very recent values.
define("DXZ25_CONVERTER_USERS_PROFILE_OVERWRITE", true);
// If set to false, user groups will contain values from the first converted Discuz!.
define("DXZ25_CONVERTER_USERS_GROUPS_OVERWRITE", true);
// If set to true, the converter will try to fix discuzcode problems.
define("DXZ25_CONVERTER_PARSER_FIX_DISCUZCODE", true);
// If set to true, all mod permissions of imported moderators will be invalidated.
define("DXZ25_CONVERTER_MODERS_INVALIDATE_ALL_PERMS", false);
/*****
 * Convert any user profilefield to MyBB? Settings are:
 * 'fid':        profilefield's target field id in MyBB table `userfields`
 *               -1: don't convert this field
 *                0: need to insert a new profile field
 *               any positive integer: existing `fid` in `userfields`
 * 'old_table':  profilefield's in which Discuz! table?
 * 'old_column': profilefield's column
 */
$DZ_USER_PROFILEFIELDS = array(
		// Should be started at index 0 and don't change any index to make import progress working right.
		0 => array(
				'name' => 'location',
				'fid' => 1,
				'old_table' => 'common_member_profile',
				'old_column' => 'address',
				),
		array(
				'name' => 'bio',
				'fid' => 2,
				'old_table' => 'common_member_profile',
				'old_column' => 'bio',
		),
		array(
				'name' => 'sex',
				'fid' => 3,
				'old_table' => 'common_member_profile',
				'old_column' => 'gender',
		),
		////////////// Any MyBB predefined user profilefiled should be added before this comment line.
		array(
				'name' => 'credits',
				'fid' => 0,
				'old_table' => 'common_member',
				'old_column' => 'credits',
		),
		array(
				'name' => 'extcredits',
				'fid' => 0,
				// Definition of extcredits_[1~8] comes from `extcredits` in `common_setting`.
				'old_def_table' => 'common_setting',
				'old_table' => 'common_member_count',
				'old_column' => 'extcredits',
		),
		array(
				'name' => 'digestposts',
				'fid' => 0,
				'old_table' => 'common_member_count',
				'old_column' => 'digestposts',
		),
		array(
				'name' => 'qq',
				'fid' => 0,
				'old_table' => 'common_member_profile',
				'old_column' => 'qq',
		),
		array(
				'name' => 'medals',
				'fid' => 0,
				// Definition of medals comes from `forum_medal`.
				'old_def_table' => 'forum_medal',
				'old_table' => 'common_member_field_forum',
				'old_column' => 'medals',
/*				'old_table' => 'common_member_medal',
				'old_column' => 'medalid',
*/		),
);

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
			"import_ucusers"			=> array("name" => "UCenter Users", "dependencies" => "db_configuration", "class_depencencies" => "users"),
			"import_users"				=> array("name" => "Users", "dependencies" => "db_configuration,import_settings,import_usergroups"),
			"import_forums"				=> array("name" => "Forums", "dependencies" => "db_configuration"),
			"import_forumperms"			=> array("name" => "Forum Permissions", "dependencies" => "db_configuration,import_forums,import_usergroups"),
			"import_threadprefixes"		=> array("name" => "Thread Prefixes", "dependencies" => "db_configuration", "class_depencencies" => "__none__"),	// Customed converter module
			"import_threads"			=> array("name" => "Threads", "dependencies" => "db_configuration,import_forums,import_users,import_threadprefixes"),
			"import_polls"				=> array("name" => "Polls", "dependencies" => "db_configuration,import_threads"),
			"import_pollvotes"			=> array("name" => "Poll Votes", "dependencies" => "db_configuration,import_polls"),
			"import_posts"				=> array("name" => "Posts", "dependencies" => "db_configuration,import_threads"),
			"import_privatemessages"	=> array("name" => "Private Messages", "dependencies" => "db_configuration,import_users"),
			"import_moderators"			=> array("name" => "Moderators", "dependencies" => "db_configuration,import_forums,import_users"),
			"import_announcements"		=> array("name" => "Announcements", "dependencies" => "db_configuration,import_users", "class_depencencies" => "__none__"),	// Customed converter module
			"import_profilefields"		=> array("name" => "Extended User Profile Fields", "dependencies" => "db_configuration", "class_depencencies" => "__none__"),	// Customed converter module
			"import_userfields"			=> array("name" => "Extended User Profile Infos", "dependencies" => "db_configuration,import_users,import_profilefields", "class_depencencies" => "__none__"),	// Customed converter module
			"import_buddies"			=> array("name" => "Buddies", "dependencies" => "db_configuration,import_users", "class_depencencies" => "users"),	// Customed converter module
/*			"import_events"				=> array("name" => "Calendar Events", "dependencies" => "db_configuration,import_posts"),
*/			"import_avatars"			=> array("name" => "Avatars", "dependencies" => "db_configuration,import_users"),
			"import_attachments"		=> array("name" => "Attachments", "dependencies" => "db_configuration,import_posts"),
			
	);
	
	/**
	 * The table we check to verify it's "our" database
	 *
	 * @var String
	 */
	var $check_table = "common_member,pm_members";
	
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
			4 => MYBB_BANNED, // Discuz!: Banned from posting
			5 => MYBB_BANNED, // Discuz!: Banned from viewing forums
			6 => MYBB_BANNED, // Discuz!: Banned from visiting whole site
			7 => MYBB_GUESTS, // Guests
			8 => MYBB_AWAITING, // Awaiting Activation
			/* Discuz! normal user groups starts here, uncomment following lines and add more to convert all non-privileged or banned/awaiting user to MYBB_REGISTERED. */
//			9 => MYBB_REGISTERED, // Registered, Discuz!: any user in this group usually has a negative credit (like post number), so some permissions are denied
//			10 => MYBB_REGISTERED, // Registered, Discuz!: a real normal registered user
	);
	
	var $column_length_to_check = array(

	);
	
	var $get_post_cache = array();
	
	function __construct()
	{
		parent::__construct();
		
		if(defined("DZX25_CONVERTER_THREADCLASS_DEPS") && DZX25_CONVERTER_THREADCLASS_DEPS && isset($this->modules))
		{
			$this->modules['import_threadprefixes']['dependencies'] = 'db_configuration,import_forums,import_usergroups';
		}
	}
	
	public function dz_unserialize($str)
	{
		$result = unserialize($str);
		if($result === false)
		{
			$result = unserialize(stripslashes($str));
		}
		return $result;
	}
	
	/**
	 * Get the uid of a given username
	 *
	 * @param string $username of a user
	 * @return int|bool The uid in MyBB or false if the username is not found.
	 */
	public function dz_get_uid($username, $encode_table = "")
	{
		global $db;
		
		$encoded_username = encode_to_utf8($username, empty($encode_table) ? "common_member" : $encode_table, "users");
		
		// Check for duplicate users
		$where = "username='".$db->escape_string($username)."' OR username='".$db->escape_string($encoded_username)."'";
		$query = $db->simple_select("users", "username,uid", $where, array('limit' => 1));
		$user = $db->fetch_array($query);
		$db->free_result($query);
		
		// Using strtolower and my_strtolower to check, instead of in the query, is exponentially faster
		// If we used LOWER() function in the query the index wouldn't be used by MySQL
		if(strtolower($user['username']) == strtolower($username) || converter_my_strtolower($user['username']) == converter_my_strtolower($encoded_username))
		{
			return $user['uid'];
		}
		
		return false;
	}
	/**
	 * Get the username of a given uid
	 *
	 * @param int $uid The uid of a user
	 * @return string The username in MyBB or false if the uid is not found.
	 */
	public function dz_get_username($uid)
	{
		global $db;
		
		// Check for duplicate users
		$query = $db->simple_select("users", "username", "uid = {$uid}", array('limit' => 1));
		$username = $db->fetch_field($query, "username");
		$db->free_result($query);
		
		if(empty($username))
		{
			return false;
		}
		
		return $username;
	}
	
	/**
	 * Get the import_uid of a given username
	 *
	 * @param string $username of a user
	 * @return int|bool The uid in old Discuz! DB or false if the username is not found.
	 */
	public function dz_get_import_uid($username, $encode_table = "")
	{
		global $db;
		
		$encoded_username = encode_to_utf8($username, empty($encode_table) ? "common_member" : $encode_table, "users");
		
		// Check for duplicate users
		$where = "username='".$db->escape_string($username)."' OR username='".$db->escape_string($encoded_username)."'";
		$query = $db->simple_select("users", "username,import_uid", $where, array('limit' => 1));
		$user = $db->fetch_array($query);
		$db->free_result($query);
		
		// Using strtolower and my_strtolower to check, instead of in the query, is exponentially faster
		// If we used LOWER() function in the query the index wouldn't be used by MySQL
		if(strtolower($user['username']) == strtolower($username) || converter_my_strtolower($user['username']) == converter_my_strtolower($encoded_username))
		{
			return $user['import_uid'];
		}
		
		return false;
	}
	/**
	 * Get a table's encoding.
	 *
	 * @param string $table_name The table name.
	 * @return string The encoding of this table.
	 */
	public function fetch_table_encoding($table_name)
	{
		$encoding = fetch_table_encoding($table_name);
		return $encoding;
	}
}


