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
/**
 * Set it to true, if you've already set up your MyBB forum settings, and then the `users` module will not require dependency 
 * on the `settings` module. Otherwise a false value of this constant will cause overwriting some forum settings with values 
 * from this import. The `users` module depends the correct setting of time zone of MyBB setting.
 */
define("DZX25_CONVERTER_MYBB_IS_SET", false);
/** 
 * Convert thread class from Discuz! to thread prefixes in MyBB without setting any permission on forum using and group using.
 * If its value is true, the converter will require dependencies of the import of forums and usergroups. Otherwise, no dependency is required.
 */
define("DZX25_CONVERTER_THREADCLASS_DEPS", true);
/**
 * Try to find email's length problem? In UCenter, a user may have a shorter email address as the email field is CHAR(32), but in Discuz! X2.5 it's CHAR(40), and in MyBB it's VARCHAR(220).
 */
define("DZUCENTER_CONVERTER_USERS_FIX_EMAIL", true);
/**
 * Define of a user's last visit/active timestamp, if they're not provided in your old database.
 */
//define("DZX25_CONVERTER_USERS_LASTTIME", 1390492800);
/**
 * Overwrite some user data when importing more than one Discuz!.
 * If set to false, user profiles will contain data mostly from the first converted Discuz!. User's last status, such as lastvisit, lastactivity, etc., will still be overwriten with very recent values.
 */
define("DXZ25_CONVERTER_USERS_PROFILE_OVERWRITE", false);
/**
 * If set to false, user groups will contain values from the first converted Discuz!.
 */
define("DXZ25_CONVERTER_USERS_GROUPS_OVERWRITE", false);
/**
 * If set to true, all mod permissions of imported moderators will be invalidated.
 */
define("DXZ25_CONVERTER_MODERS_INVALIDATE_ALL_PERMS", false);
/**
 * If set to true, the converter will try to fix discuzcode problems.
 */
define("DXZ25_CONVERTER_PARSER_FIX_DISCUZCODE", true);
/**
 * The default font name for [font=*] discuzcode of a Chinese font that can't be handled. Comment this define if you want unhandled font name tag to be get rid of.
 */
define("DXZ25_CONVERTER_PARSER_DEFAULT_FONTS", "Microsoft YaHei, PingFang, STXihei, Droid Sans, WenQuanYi Micro Hei");
/**
 * Re-check an attachment file's mime type using PHP mime_content_type after it has been stored in MyBB uploads. This action does rely on your PHP's ability, thus if you use an old version of PHP, better turn this define to false.
 */
define("DXZ25_CONVERTER_DZX_UPLOAD_RECHECK_MIME_TYPE", false);


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
			"import_profilefields"		=> array("name" => "Extended User Profile Fields", "dependencies" => "db_configuration", "class_depencencies" => "__none__"),	// Customized converter module
			"import_userfields"			=> array("name" => "Extended User Profile Information", "dependencies" => "db_configuration,import_users,import_profilefields", "class_depencencies" => "__none__"),	// Customized converter module
			"import_announcements"		=> array("name" => "Announcements", "dependencies" => "db_configuration,import_users", "class_depencencies" => "__none__"),	// Customized converter module
			"import_threadprefixes"		=> array("name" => "Thread Prefixes", "dependencies" => "db_configuration", "class_depencencies" => "__none__"),	// Customized converter module
			"import_forums"				=> array("name" => "Forums", "dependencies" => "db_configuration"),
			"import_forumperms"			=> array("name" => "Forum Permissions", "dependencies" => "db_configuration,import_forums,import_usergroups"),
			"import_moderators"			=> array("name" => "Moderators", "dependencies" => "db_configuration,import_forums,import_users"),
			"import_threads"			=> array("name" => "Threads", "dependencies" => "db_configuration,import_forums,import_users,import_threadprefixes"),
			"import_polls"				=> array("name" => "Polls", "dependencies" => "db_configuration,import_threads"),
			"import_pollvotes"			=> array("name" => "Poll Votes", "dependencies" => "db_configuration,import_polls"),
			"import_posts"				=> array("name" => "Posts", "dependencies" => "db_configuration,import_threads"),
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
	 * An array of discuz -> mybb groups
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
			/***
			 * Discuz! normal user groups starts here, uncomment following lines 
			 * and add more to convert all non-privileged or banned/awaiting user 
			 * to MYBB_REGISTERED.
			 * 9 => MYBB_REGISTERED, Registered, Discuz!: any user in this group usually has a negative credit, so some permissions are denied.
			 * 10 => MYBB_REGISTERED, // Registered, Discuz!: a real normal registered user.
			 */
//			9 => MYBB_REGISTERED,
//			10 => MYBB_REGISTERED,
	);
	
	var $column_length_to_check = array(

	);
	
	/**
	 * Cache for threadprefixes
	 */
	var $cache_threadprefixes;
	
	/*****
	 * Convert any user profilefield to MyBB? Settings are:
	 * 'fid':        profilefield's target field id in MyBB table `userfields`
	 *               -1: don't convert this field
	 *                0: need to insert a new profile field
	 *               any positive integer: existing `fid` in `userfields`
	 * 'old_table':  profilefield's in which Discuz! table?
	 * 'old_column': profilefield's column
	 */
	var $DZ_USER_PROFILEFIELDS = array(
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
					// Definition of medals comes from `forum_medal`. `common_member_medal` contains granting medal lists.
					'old_def_table' => 'forum_medal',
					'old_table' => 'common_member_field_forum',
					'old_column' => 'medals',
			),
	);
	
	function __construct()
	{
		parent::__construct();
		
		if(defined("DZX25_CONVERTER_THREADCLASS_DEPS") && DZX25_CONVERTER_THREADCLASS_DEPS && isset($this->modules))
		{
			$this->modules['import_threadprefixes']['dependencies'] = 'db_configuration,import_forums,import_usergroups';
		}
		if(defined("DZX25_CONVERTER_MYBB_IS_SET") && !DZX25_CONVERTER_MYBB_IS_SET && isset($this->modules))
		{
			$this->modules['import_users']['dependencies'] = 'db_configuration,import_settings,import_usergroups';
		}
	}
	
	/**
	 * Properly converts the encoding of a string based upon the old table to the new table to utf8 encoding, as best as we can
	 *
	 * @param string $text The text to convert
	 * @param string $old_table_name The old table (e.x. vB's user table)
	 * @param string $new_table_name The new table (e.x. MyBB's user table)
	 * @return string The converted text in utf8 format
	 */
	function encode_to_utf8($text, $old_table_name, $new_table_name)
	{
		global $import_session, $db, $module;
		
		if($import_session['encode_to_utf8'] == 0)
		{
			return $text;
		}
		
		$old_table_name = OLD_TABLE_PREFIX.$old_table_name;
		$new_table_name = TABLE_PREFIX.$new_table_name;
		
		// Get the character set if needed
		if(empty($import_session['table_charset_old'][$old_table_name]) || empty($import_session['table_charset_new'][$new_table_name]))
		{
			$old_table_prefix = $db->table_prefix;
			$db->set_table_prefix('');
			
			$old_old_db_table_prefix = $module->old_db->table_prefix;
			$module->old_db->set_table_prefix('');
			
			$table = $module->old_db->show_create_table($old_table_name);
			$old_charset = array();
			preg_match("#CHARSET=(\S*)#i", $table, $old_charset);
			
			$table = $db->show_create_table($new_table_name);
			$new_charset = array();
			preg_match("#CHARSET=(\S*)#i", $table, $new_charset);
			
			$db->set_table_prefix($old_table_prefix);
			$module->old_db->set_table_prefix($old_old_db_table_prefix);
			
			$import_session['table_charset_old'][$old_table_name] = $old_charset[1];
			$import_session['table_charset_new'][$new_table_name] = $new_charset[1];
		}
		
		// Convert as needed
		if(($import_session['table_charset_new'][$new_table_name] != $import_session['table_charset_old'][$old_table_name]
				|| check_encoding($text, fetch_iconv_encoding($import_session['table_charset_new'][$new_table_name])) === false)
				&& $import_session['table_charset_old'][$old_table_name] != ''
				&& $import_session['table_charset_new'][$new_table_name] != '')
		{
			if(!function_exists('iconv'))
			{
				if(fetch_iconv_encoding($import_session['table_charset_old'][$old_table_name]) != 'iso-8859-1' || !function_exists("utf8_encode"))
				{
					return $text;
				}
				
				return utf8_encode($text);
			}
			
			$converted_str = iconv(fetch_iconv_encoding($import_session['table_charset_old'][$old_table_name]), fetch_iconv_encoding($import_session['table_charset_new'][$new_table_name]).'//TRANSLIT', $text);
			
			// Do we have bad characters? (i.e. db/table encoding set to UTF-8 but string is actually ISO)
			if($this->converter_my_strlen($converted_str) < $this->converter_my_strlen($text, $this->fetch_mbstring_encoding($import_session['table_charset_old'][$old_table_name])))
			{
				// Was our database/tables set to UTF-8 encoding and the data actually in iso encoding?
				// Stop trying to confuse us!!
				$converted_str = iconv("iso-8859-1", fetch_iconv_encoding($import_session['table_charset_new'][$new_table_name]).'//IGNORE', $text);
				if($this->converter_my_strlen($converted_str) >= $this->converter_my_strlen($text, $this->fetch_mbstring_encoding($import_session['table_charset_old'][$old_table_name])))
				{
					return $converted_str;
				}
			}
			
			// Try to convert, but don't stop when a character cannot be converted
			return iconv(fetch_iconv_encoding($import_session['table_charset_old'][$old_table_name]), fetch_iconv_encoding($import_session['table_charset_new'][$new_table_name]).'//IGNORE', $text);
		}
		
		return $text;
	}
	
	/**
	 * Checks for the length of a string, mb strings accounted for.
	 *
	 * Added here replacing the original my_strlen() function in MyBB,
	 * to deal with problematic converting of Chinese characters.
	 *
	 * @param string $string The string to check the length of.
	 * @param string $string The encoding of $string, see https://www.php.net/manual/en/mbstring.supported-encodings.php
	 * @return int The length of the string.
	 */
	function converter_my_strlen($string, $mb_encoding = "")
	{
		global $lang;
		
		$string = preg_replace("#&\#([0-9]+);#", "-", $string);
		
		if(strtolower($lang->settings['charset']) == "utf-8")
		{
			// Get rid of any excess RTL and LTR override for they are the workings of the devil
			$string = str_replace(dec_to_utf8(8238), "", $string);
			$string = str_replace(dec_to_utf8(8237), "", $string);
			
			// Remove dodgy whitespaces
			$string = str_replace(chr(0xCA), "", $string);
		}
		$string = trim($string);
		
		if(function_exists("mb_strlen"))
		{
			// When counting Chinese characters in GBK encoding, mb_strlen() acts weird without
			// an encoding parameter, i.e., using internal encoding, if it's UTF-8.
			if(!isset($mb_encoding) || empty($mb_encoding))
			{
				$mb_encoding = mb_detect_encoding($string, mb_detect_order(), true);
			}
			$string_length = mb_strlen($string, $mb_encoding);
		}
		else
		{
			$string_length = strlen($string);
		}
		
		return $string_length;
	}
	
	/**
	 * Lowers the case of a string, mb strings accounted for
	 *
	 * @param string $string The string to lower.
	 * @param string $string The encoding of $string, see https://www.php.net/manual/en/mbstring.supported-encodings.php
	 * @return string The lowered string.
	 */
	function converter_my_strtolower($string, $mb_encoding = "")
	{
		if(function_exists("mb_strtolower"))
		{
			// When counting Chinese characters in GBK encoding, mb_strlen() acts weird without
			// an encoding parameter, i.e., using internal encoding, if it's UTF-8.
			if(!isset($mb_encoding) || empty($mb_encoding))
			{
				$mb_encoding = mb_detect_encoding($string, mb_detect_order(), true);
			}
			$string = mb_strtolower($string, $mb_encoding);
		}
		else
		{
			$string = strtolower($string);
		}
		
		return $string;
	}
	
	/**
	 * Converts the given MySQL encoding to a PHP mbstring usable encoding
	 *
	 * @param string $mysql_encoding The MySQL encoding
	 * @return string The mbstring encoding
	 */
	function fetch_mbstring_encoding($mysql_encoding)
	{
		$mysql_encoding = explode("_", $mysql_encoding);
		switch($mysql_encoding[0])
		{
			case "utf8":
			case "utf8mb4":
				return "UTF-8";
				break;
			case "latin1":
				return "ISO-8859-1";
				break;
			case "gbk":
				return "GB18030";	// Change to "GB18030" if you experience any problematic Chinese character converting, also requiring PHP >= 5.4.0. Otherwise, use "GB2312" instead.
				break;
			default:
				return strtoupper($mysql_encoding[0]);
		}
	}
	
	/**
	 * Finds a table's encoding.
	 *
	 * @param string $table_name The table name.
	 * @param bool $old_table Optional, if it's a MyBB table, set it to false.
	 * @return string The encoding of this table.
	 */
	public function fetch_table_encoding($table_name, $old_table = true)
	{
		global $import_session, $db, $module;
		
		if($old_table)
		{
			$table_name = OLD_TABLE_PREFIX.$table_name;
		}
		else
		{
			$table_name = TABLE_PREFIX.$table_name;
		}
		
		if($old_table && empty($import_session['table_charset_old'][$table_name]))
		{
			$old_old_db_table_prefix = $module->old_db->table_prefix;
			$module->old_db->set_table_prefix('');
			
			$table = $module->old_db->show_create_table($table_name);
			$old_charset = array();
			preg_match("#CHARSET=(\S*)#i", $table, $old_charset);
			$module->old_db->set_table_prefix($old_old_db_table_prefix);
			
			$import_session['table_charset_old'][$table_name] = $old_charset[1];
		}
		else if(!$old_table && empty($import_session['table_charset_new'][$table_name]))
		{
			$old_table_prefix = $db->table_prefix;
			$db->set_table_prefix('');
			
			$table = $db->show_create_table($table_name);
			$new_charset = array();
			preg_match("#CHARSET=(\S*)#i", $table, $new_charset);
			$db->set_table_prefix($old_table_prefix);
			
			$import_session['table_charset_new'][$table_name] = $new_charset[1];
		}
		
		$mysql_encoding = $old_table ? $import_session['table_charset_old'][$table_name] : $import_session['table_charset_new'][$table_name];
		
		$mysql_encoding = explode("_", $mysql_encoding);
		switch($mysql_encoding[0])
		{
			case "utf8":
			case "utf8mb4":
				return "UTF-8";
				break;
			case "latin1":
				return "ISO-8859-1";
				break;
			default:
				return $mysql_encoding[0];
		}
	}
	
	/**
	 * Unserialize function specialized in Discuz! X2.5
	 *
	 * @param string $str The serialized string of an array.
	 * @return mixed The unserialize array or other types of values.
	 */
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
		
		$encoded_username = $this->encode_to_utf8($username, empty($encode_table) ? "common_member" : $encode_table, "users");
		
		// Check for duplicate users
		$where = "username='".$db->escape_string($username)."' OR username='".$db->escape_string($encoded_username)."'";
		$query = $db->simple_select("users", "username,uid", $where, array('limit' => 1));
		$user = $db->fetch_array($query);
		$db->free_result($query);
		
		// Using strtolower and my_strtolower to check, instead of in the query, is exponentially faster
		// If we used LOWER() function in the query the index wouldn't be used by MySQL
		if(strtolower($user['username']) == strtolower($username) || $this->converter_my_strtolower($user['username']) == $this->converter_my_strtolower($encoded_username))
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
	 * Get an array of imported threadprefixes (e.x. array Discuz! threadclass typeid => MyBB threadprefixes pid)
	 *
	 * @return array|false
	 */
	function cache_threadprefixes()
	{
		global $import_session;
		
		$prefixes = array();
		
		if(isset($import_session['imported_threadprefix']))
		{
			foreach($import_session['imported_threadprefix'] as $pid => $imported_pids)
			{
				$prefix_ids = explode(",", $imported_pids);
				foreach($prefix_ids as $prefix_id)
				{
					$prefixes[empty($prefix_id) ? 0 : $prefix_id] = $pid;
				}
			}
		}
		
		return $prefixes;
	}
	
	/**
	 * Get the MyBB threadprefix ID of an old threadprefix. (e.x. Discuz! threadclass typeid)
	 *
	 * @param int $old_threadprefix Post prefixclass ID used before import
	 * @return int Post prefix ID in MyBB
	 */
	public function threadprefix($old_threadprefix)
	{
		if(!is_array($this->cache_threadprefixes))
		{
			$this->cache_threadprefixes();
		}
		
		if(!isset($this->cache_threadprefixes[$old_threadprefix]) || $old_threadprefix == 0)
		{
			return 0;
		}
		
		return $this->cache_threadprefixes[$old_threadprefix];
	}
	
	// Functions below are reserved for future use.
	/**
	 * Get the import_uid of a given username
	 *
	 * @param string $username of a user
	 * @return int|bool The uid in old Discuz! DB or false if the username is not found.
	 */
	public function dz_get_import_uid($username, $encode_table = "")
	{
		global $db;
		
		$encoded_username = $this->encode_to_utf8($username, empty($encode_table) ? "common_member" : $encode_table, "users");
		
		// Check for duplicate users
		$where = "username='".$db->escape_string($username)."' OR username='".$db->escape_string($encoded_username)."'";
		$query = $db->simple_select("users", "username,import_uid", $where, array('limit' => 1));
		$user = $db->fetch_array($query);
		$db->free_result($query);
		
		// Using strtolower and my_strtolower to check, instead of in the query, is exponentially faster
		// If we used LOWER() function in the query the index wouldn't be used by MySQL
		if(strtolower($user['username']) == strtolower($username) || $this->converter_my_strtolower($user['username']) == $this->converter_my_strtolower($encoded_username))
		{
			return $user['import_uid'];
		}
		
		return false;
	}
}


