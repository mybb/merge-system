<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 */

class Converter_Module_Users extends Converter_Module
{
	public $default_values = array(
		'usergroup' => 0,
		'additionalgroups' => '',
		'displaygroup' => 0,
		'import_usergroup' => 0,
		'import_additionalgroups' => '',
		'import_displaygroup' => 0,
		'import_uid' => 0,
		'username' => '',
		'password' => '',
		'salt' => '',
		'loginkey' => '',
		'email' => '',
		'regdate' => 0,
		'lastactive' => 0,
		'lastvisit' => 0,
		'website' => '',
		'showsigs' => 1,
		'signature' => '',
		'showavatars' => 1,
		'timezone' => '',
		'avatardimensions' => '',
		'avatartype' => '',
		'avatar' => '',
		'lastpost' => 0,
		'icq' => '',
		'aim' => '',
		'yahoo' => '',
		'skype' => '',
		'google' => '',
		'hideemail' => 1,
		'allownotices' => 1,
		'regip' => '',
		'lastip' => '',
		'language' => '',
		'passwordconvert' => '',
		'passwordconverttype' => '',
		'postnum' => 0,
		'invisible' => 0,
		'birthday' => '',
		'birthdayprivacy' => 'all',
		'subscriptionmethod' => 2,
		'receivepms' => 1,
		'receivefrombuddy' => 0,
		'pmnotice' => 1,
		'pmnotify' => 1,
		'showquickreply' => 1,
		'ppp' => 0,
		'tpp' => 0,
		'daysprune' => 0,
		'timeformat' => '',
		'dst' => 0,
		'buddylist' => '',
		'ignorelist' => '',
		'style' => 0,
		'away' => 0,
		'awaydate' => 0,
		'returndate' => '',
		'referrer' => 0,
		'referrals' => 0,
		'reputation' => 0,
		'timeonline' => 0,
		'showcodebuttons' => 1,
		'totalpms' => 0,
		'unreadpms' => 0,
		'pmfolders' => '1**Inbox$%%$2**Sent Items$%%$3**Drafts$%%$4**Trash Can',
		'notepad' => '',
		'threadmode' => '',
		'showredirect' => 1,
		'dateformat' => '',
		'dstcorrection' => 1,
		'warningpoints' => 0,
		'moderateposts' => 0,
		'moderationtime' => 0,
		'suspendposting' => 0,
		'suspensiontime' => 0,
		'suspendsignature' => 0,
		'suspendsigtime' => 0,
		'coppauser' => 0,
		'classicpostbit' => 0,
		'loginattempts' => 0,
		'usernotes' => '',
		'showimages' => 1,
		'showvideos' => 1,
	);
	
	public $binary_fields = array(
		'regip',
		'lastip',
	);

	public $integer_fields = array(
		'usergroup',
		'displaygroup',
		'import_usergroup',
		'import_displaygroup',
		'import_uid',
		'regdate',
		'lastactive',
		'lastvisit',
		'showsigs',
		'showavatars',
		'lastpost',
		'hideemail',
		'allownotices',
		'postnum',
		'invisible',
		'subscriptionmethod',
		'receivepms',
		'receivefrombuddy',
		'pmnotice',
		'pmnotify',
		'showquickreply',
		'ppp',
		'tpp',
		'daysprune',
		'dst',
		'style',
		'away',
		'awaydate',
		'referrer',
		'referrals',
		'reputation',
		'timeonline',
		'showcodebuttons',
		'totalpms',
		'unreadpms',
		'showredirect',
		'dstcorrection',
		'warningpoints',
		'moderateposts',
		'moderationtime',
		'suspendposting',
		'suspensiontime',
		'suspendsignature',
		'suspendsigtime',
		'coppauser',
		'classicpostbit',
		'loginattempts',
		'showimages',
		'showvideos',
	);
	
	/**
	 * Total users queried from the MyBB Database used in the users module
	 */
	public $total_users = 0;

	/**
	 * Insert user into database
	 *
	 * @param user The insert array going into the MyBB database
	 */
	public function insert($data)
	{
		global $db, $output;

		if(!$this->check_for_duplicates($data))
		{
			$this->increment_tracker('users');
			return;
		}

		++$this->total_users;

		$this->debug->log->datatrace('$data', $data);

		$output->print_progress("start", $data[$this->settings['progress_column']]);

		// Call our currently module's process function
		$data = $this->convert_data($data);

		// Should loop through and fill in any values that aren't set based on the MyBB db schema or other standard default values and escape them properly
		$insert_array = $this->prepare_insert_array($data);

		$this->debug->log->datatrace('$insert_array', $insert_array);

		$db->insert_query("users", $insert_array);
		$uid = $db->insert_id();

		$this->increment_tracker('users');

		$output->print_progress("end");

		return $uid;
	}

	public function check_for_duplicates(&$user)
	{
		global $db, $output, $import_session;

		if(!$this->total_users)
		{
			// Count the total number of users so we can generate a unique id if we have a duplicate user
			$query = $db->simple_select("users", "COUNT(*) as totalusers");
			$this->total_users = $db->fetch_field($query, "totalusers");
			$db->free_result($query);
		}

		$username = $user[$this->settings['username_column']];
		$encoded_username = encode_to_utf8($user[$this->settings['username_column']], $this->settings['encode_table'], "users");

		// Check for duplicate users
		$where = "username='".$db->escape_string($username)."' OR username='".$db->escape_string($encoded_username)."'";
		$query = $db->simple_select("users", "username,email,uid,postnum", $where, array('limit' => 1));
		$duplicate_user = $db->fetch_array($query);
		$db->free_result($query);

		// Using strtolower and my_strtolower to check, instead of in the query, is exponentially faster
		// If we used LOWER() function in the query the index wouldn't be used by MySQL
		if(strtolower($duplicate_user['username']) == strtolower($username) || my_strtolower($duplicate_user['username']) == strtolower($encoded_username))
		{
			if($user[$this->settings['email_column']] == $duplicate_user['email'])
			{
				$output->print_progress("start");
				$output->print_progress("merge_user", array('import_uid' => $user[$this->settings['progress_column']], 'duplicate_uid' => $duplicate_user['uid']));

				$db->update_query("users", array('import_uid' => $user[$this->settings['progress_column']], 'postnum' => $duplicate_user['postnum']+$user[$this->settings['postnum_column']]), "uid = '{$duplicate_user['uid']}'");

				return false;
			}
			else
			{
				$user[$this->settings['username_column']] = $duplicate_user['username']."_".$import_session['board']."_import".$this->total_users;
			}
		}

		return true;
	}
}

?>