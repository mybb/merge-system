<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 *
 * IPS5 (Invision Community 5) Converter - SKELETON
 * IMPORTANT: This converter has NOT been tested against a real IPS5 database.
 * Column names are based on the IPS4 converter and need verification.
 * Search for TODO comments to find unverified mappings.
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class IPB5_Converter extends Converter {

	/**
	 * String of the bulletin board name
	 *
	 * @var string
	 */
	var $bbname = "Invision Community 5";

	/**
	 * String of the plain bulletin board name
	 *
	 * @var string
	 */
	var $plain_bbname = "Invision Community 5";

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
						 "import_forums" => array("name" => "Forums", "dependencies" => "db_configuration,import_users"),
						 "import_forumperms" => array("name" => "Forum Permissions", "dependencies" => "db_configuration,import_forums"),
						 "import_threads" => array("name" => "Threads", "dependencies" => "db_configuration,import_forums"),
						 "import_polls" => array("name" => "Polls", "dependencies" => "db_configuration,import_threads"),
						 "import_pollvotes" => array("name" => "Poll Votes", "dependencies" => "db_configuration,import_polls"),
						 "import_posts" => array("name" => "Posts", "dependencies" => "db_configuration,import_threads"),
						 "import_privatemessages" => array("name" => "Private Messages", "dependencies" => "db_configuration,import_users"),
						 "import_settings" => array("name" => "Settings", "dependencies" => "db_configuration"),
						 "import_avatars" => array("name" => "Avatars", "dependencies" => "db_configuration,import_users"),
						 "import_attachments" => array("name" => "Attachments", "dependencies" => "db_configuration,import_posts"),
						);

	/**
	 * The table we check to verify it's "our" database
	 * TODO: Verify this table exists in IPS5 (might be core_members or another table)
	 *
	 * @var String
	 */
	var $check_table = "core_members";

	/**
	 * The table prefix we suggest to use
	 * TODO: Verify IPS5 table prefix convention
	 *
	 * @var String
	 */
	var $prefix_suggestion = "";

	/**
	 * An array of IPS5 -> mybb groups
	 * TODO: Verify group IDs in IPS5 (these may have changed from IPS4)
	 *
	 * @var array
	 */
	var $groups = array(
		2 => MYBB_GUESTS, // Guests
		3 => MYBB_REGISTERED, // Registered
		4 => MYBB_ADMINS, // Administrators
		6 => MYBB_MODS, // Moderators
	);

	/**
	 * An array of supported databases
	 * IPS5 only supports MySQL
	 */
	var $supported_databases = array("mysql");

	private $defaultLanguage = null;

	/**
	 * Retrieve a localized language string from IPS5's translation system
	 * TODO: Verify IPS5 language table structure (core_sys_lang, core_sys_lang_words may have changed)
	 *
	 * @param string $key      The language key to retrieve
	 * @param string $app      The app where the key belongs to. Defaults to 'core'
	 * @param null $plugin     The plugin the key belongs to
	 * @param string $language The language to be used. Defaults to the board default
	 * @param bool $default    Whether the default or custom language string should be used
	 *
	 * @return string
	 */
	public function getLanguageString($key, $app = 'core', $plugin = null, $language = 'default', $default = false)
	{
		if($language == 'default')
		{
			if($this->defaultLanguage == null)
			{
				$query = $this->old_db->simple_select('core_sys_lang', 'lang_id', 'lang_default=1');
				$this->defaultLanguage = $this->old_db->fetch_field($query, 'lang_id');
			}

			$language = $this->defaultLanguage;
		}
		elseif(!is_int($language))
		{
			$language = (string)$language;
			$query = $this->old_db->simple_select('core_sys_lang', 'lang_id', "lang_short='".$this->old_db->escape_string($language)."'");

			if($this->old_db->num_rows($query) != 1)
			{
				return $key;
			}

			$language = $this->old_db->fetch_field($query, 'lang_id');
		}

		$where = "word_key='".$this->old_db->escape_string($key)."' AND lang_id={$language} AND word_app='".$this->old_db->escape_string($app)."'";

		if($plugin != null)
		{
			$where .= " AND word_plugin=".(int)$plugin;
		}
		else
		{
			$where .= ' AND word_plugin IS NULL';
		}

		$query = $this->old_db->simple_select('core_sys_lang_words', 'word_default,word_custom', $where);
		$word = $this->old_db->fetch_array($query);

		if($default || empty($word['word_custom']))
		{
			return $word['word_default'];
		}

		return $word['word_custom'];
	}
}
