<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 *
 * Discourse User Converter
 * Discourse stores user data across users, user_emails, and user_profiles tables.
 * Passwords are bcrypt hashes (Ruby has_secure_password format, $2a$ prefix).
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class DISCOURSE_Converter_Module_Users extends Converter_Module_Users {

	var $settings = array(
		'friendly_name' => "users",
		'progress_column' => "id",
		'encode_table' => "users",
		'postnum_column' => "post_count",
		'username_column' => 'username',
		'email_column' => 'email',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;

		// Get users with primary email and profile data
		// Discourse may have staged users (imported from other systems) - we include them
		$query = $this->old_db->query("
			SELECT u.*, ue.email, up.website, up.location, up.bio_raw
			FROM ".OLD_TABLE_PREFIX."users u
			LEFT JOIN ".OLD_TABLE_PREFIX."user_emails ue ON (ue.user_id = u.id AND ue.primary = TRUE)
			LEFT JOIN ".OLD_TABLE_PREFIX."user_profiles up ON (up.user_id = u.id)
			ORDER BY u.id ASC
			LIMIT ".$import_session['users_per_screen']." OFFSET ".$this->trackers['start_users']."
		");
		while($user = $this->old_db->fetch_array($query))
		{
			$this->insert($user);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// Discourse values
		$insert_data['import_uid'] = $data['id'];
		$insert_data['username'] = encode_to_utf8($data['username'], "users", "users");
		$insert_data['email'] = $data['email'];
		$insert_data['regdate'] = strtotime($data['created_at']);
		$insert_data['lastactive'] = !empty($data['last_seen_at']) ? strtotime($data['last_seen_at']) : 0;
		$insert_data['lastvisit'] = !empty($data['last_seen_at']) ? strtotime($data['last_seen_at']) : 0;
		$insert_data['lastpost'] = !empty($data['last_posted_at']) ? strtotime($data['last_posted_at']) : 0;
		$insert_data['website'] = $data['website'];
		$insert_data['postnum'] = $data['post_count'];
		$insert_data['timeonline'] = $data['time_read'];

		// Location (from user_profiles)
		if(!empty($data['location']))
		{
			$insert_data['fid1'] = $data['location'];
		}

		// Birthday
		if(!empty($data['date_of_birth']))
		{
			$dob = date('d-m-Y', strtotime($data['date_of_birth']));
			$insert_data['birthday'] = $dob;
		}

		// Bio becomes signature
		if(!empty($data['bio_raw']))
		{
			$insert_data['signature'] = encode_to_utf8($data['bio_raw'], "user_profiles", "users");
		}
		elseif(!empty($data['bio_cooked']))
		{
			$insert_data['signature'] = encode_to_utf8($this->bbcode_parser->convert($data['bio_cooked']), "users", "users");
		}

		// IP addresses
		if(!empty($data['ip_address']))
		{
			$insert_data['lastip'] = my_inet_pton($data['ip_address']);
		}
		if(!empty($data['registration_ip_address']))
		{
			$insert_data['regip'] = my_inet_pton($data['registration_ip_address']);
		}

		// Usergroup mapping based on admin/moderator flags and trust level
		if($data['admin'] == 't' || $data['admin'] == 1)
		{
			$insert_data['usergroup'] = MYBB_ADMINS;
		}
		elseif($data['moderator'] == 't' || $data['moderator'] == 1)
		{
			$insert_data['usergroup'] = MYBB_MODS;
		}
		else
		{
			$tl = (int)$data['trust_level'];
			if($tl >= 3)
			{
				// Trust Level 3 (Regular) or 4 (Leader) → map to imported custom groups
				$gid = $this->board->get_gid('tl'.$tl);
				$insert_data['usergroup'] = $gid;
				$insert_data['displaygroup'] = $gid;
			}
			else
			{
				$insert_data['usergroup'] = MYBB_REGISTERED;
			}
		}

		// Additional groups from Discourse group memberships
		$additional_groups = $this->get_user_groups($data['id']);
		if(!empty($additional_groups))
		{
			$insert_data['additionalgroups'] = $additional_groups;
		}

		// Password - Discourse uses bcrypt via Ruby's has_secure_password ($2a$ prefix)
		if(!empty($data['password_hash']))
		{
			$insert_data['passwordconvert'] = $data['password_hash'];
			$insert_data['passwordconverttype'] = 'discourse';
		}

		return $insert_data;
	}

	function get_user_groups($user_id)
	{
		$groups = array();
		$query = $this->old_db->simple_select("group_users", "group_id", "user_id = '{$user_id}'");
		while($g = $this->old_db->fetch_array($query))
		{
			$gid = $this->board->get_gid($g['group_id']);
			if($gid)
			{
				$groups[] = $gid;
			}
		}
		$this->old_db->free_result($query);

		return !empty($groups) ? implode(',', $groups) : '';
	}

	function fetch_total()
	{
		global $import_session;

		if(!isset($import_session['total_users']))
		{
			$query = $this->old_db->simple_select("users", "COUNT(*) as count");
			$import_session['total_users'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_users'];
	}
}
