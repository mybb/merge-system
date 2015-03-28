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

class VBULLETIN4_Converter_Module_Users extends Converter_Module_Users {

	var $settings = array(
		'friendly_name' => 'users',
		'progress_column' => 'userid',
		'encode_table' => 'user',
		'postnum_column' => 'posts',
		'username_column' => 'username',
		'email_column' => 'email',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;

		// Get members
		$query = $this->old_db->simple_select("user", "*", "", array('order_by' => 'userid', 'order_dir' => 'asc', 'limit_start' => $this->trackers['start_users'], 'limit' => $import_session['users_per_screen']));
		while($user = $this->old_db->fetch_array($query))
		{
			$this->insert($user);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// vBulletin 4 values
		$insert_data['usergroup'] = $this->board->get_gid($data['usergroupid']);
		$insert_data['additionalgroups'] = $this->board->get_group_id($data['membergroupids']);
		if($data['displaygroupid'] > 0)
		{
			$insert_data['displaygroup'] = $this->board->get_gid($data['displaygroupid']);
		}
		$insert_data['import_usergroup'] = $data['usergroupid'];
		$insert_data['import_additionalgroups'] = $data['membergroupids'];
		$insert_data['import_displaygroup'] = $data['displaygroupid'];
		$insert_data['import_uid'] = $data['userid'];
		$insert_data['username'] = encode_to_utf8($data['username'], "user", "users");
		$insert_data['email'] = $data['email'];
		$insert_data['regdate'] = $data['joindate'];
		$insert_data['lastactive'] = $data['lastactivity'];
		$insert_data['lastvisit'] = $data['lastvisit'];
		$insert_data['website'] = $data['homepage'];
		$insert_data['lastpost'] = $data['lastpost'];
		$data['birthday'] = trim($data['birthday']);
		if(!empty($data['birthday']))
		{
			list($bmonth, $bday, $byear) = explode("-", $data['birthday']);
			$insert_data['birthday'] = $bday."-".$bmonth."-".$byear;
		}
		// Don't ask me why some guys insert an ICQ number with more than 9 characters, but we need to avoid sql errors...
		$insert_data['icq'] = substr($data['icq'], 0, 10);
		$insert_data['aim'] = $data['aim'];
		$insert_data['yahoo'] = $data['yahoo'];
		$insert_data['skype'] = $data['skype'];
		$insert_data['timezone'] = str_replace(array('.0', '.00'), array('', ''), $insert_data['timezone']);
		$insert_data['style'] = 0;
		$insert_data['referrer'] = $data['referrerid'];
		$insert_data['regip'] = my_inet_pton($data['ipaddress']);
		$insert_data['totalpms'] = $data['pmtotal'];
		$insert_data['unreadpms'] = $data['pmunread'];
		$insert_data['passwordconvert'] = $data['password'];
		$insert_data['passwordconverttype'] = 'vb4';
		$insert_data['passwordconvertsalt'] = $data['salt'];
		$insert_data['signature'] = encode_to_utf8($this->bbcode_parser->convert($this->get_signature($data['userid'])), "user", "users");

		return $insert_data;
	}

	/**
	 * Get a signature from a user in the vB database
	 *
	 * @param int User ID
	 * @return array The signature
	 */
	function get_signature($userid)
	{
		$userid = intval($userid);
		$query = $this->old_db->simple_select("usertextfield", "signature", "userid = '{$userid}'", array('limit' => 1));
		return $this->old_db->fetch_field($query, "signature");
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of members
		if(!isset($import_session['total_users']))
		{
			$query = $this->old_db->simple_select("user", "COUNT(*) as count");
			$import_session['total_users'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_users'];
	}
}

?>