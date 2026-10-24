<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 *
 * Discourse Converter
 * Discourse is a modern open-source forum platform built with Ruby on Rails.
 * It uses PostgreSQL exclusively. Content is stored as Markdown (raw) and HTML (cooked).
 * Passwords are bcrypt hashes managed by Ruby's has_secure_password.
 * Trust levels (0-4) replace traditional usergroups; admin/moderator are boolean flags.
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class DISCOURSE_Converter extends Converter {

	var $bbname = "Discourse";

	var $plain_bbname = "Discourse";

	var $requires_loginconvert = true;

	var $modules = array("db_configuration" => array("name" => "Database Configuration", "dependencies" => ""),
						 "import_usergroups" => array("name" => "Usergroups", "dependencies" => "db_configuration"),
						 "import_users" => array("name" => "Users", "dependencies" => "db_configuration,import_usergroups"),
						 "import_forums" => array("name" => "Forums", "dependencies" => "db_configuration,import_users"),
						 "import_threads" => array("name" => "Threads", "dependencies" => "db_configuration,import_forums"),
						 "import_posts" => array("name" => "Posts", "dependencies" => "db_configuration,import_threads"),
						 "import_polls" => array("name" => "Polls", "dependencies" => "db_configuration,import_threads"),
						 "import_pollvotes" => array("name" => "Poll Votes", "dependencies" => "db_configuration,import_polls"),
						 "import_privatemessages" => array("name" => "Private Messages", "dependencies" => "db_configuration,import_users"),
						 "import_moderators" => array("name" => "Moderators", "dependencies" => "db_configuration,import_forums,import_users"),
						 "import_avatars" => array("name" => "Avatars", "dependencies" => "db_configuration,import_users"),
						 "import_attachments" => array("name" => "Attachments", "dependencies" => "db_configuration,import_posts"),
						);

	var $check_table = "users";

	var $prefix_suggestion = "";

	/**
	 * Discourse trust levels mapped to MyBB groups.
	 * TL0 (New) → Registered, TL1 (Basic) → Registered, TL2 (Member) → Registered
	 * TL3 (Regular) and TL4 (Leader) will be mapped to custom imported groups.
	 * Admin and Moderator are boolean flags on the user, not groups.
	 */
	var $groups = array(
		-1 => MYBB_GUESTS,    // Anonymous/guests
		0  => MYBB_REGISTERED, // Trust Level 0 - New
		1  => MYBB_REGISTERED, // Trust Level 1 - Basic
		2  => MYBB_REGISTERED, // Trust Level 2 - Member
		10 => MYBB_ADMINS,     // Virtual group for Discourse admins
		11 => MYBB_MODS,       // Virtual group for Discourse moderators
	);

	/**
	 * Discourse uses PostgreSQL exclusively.
	 */
	var $supported_databases = array("pgsql");
}
