<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2011 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class VBULLETIN3_Converter_Module_Users extends Converter_Module_Users {

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

		// vBulletin 3 values
		$insert_data['usergroup'] = $this->board->get_group_id($data['usergroupid'], array("not_multiple" => true));
		$insert_data['additionalgroups'] = str_replace($insert_data['usergroup'], '', $this->board->get_group_id($data['usergroupid']));
		$insert_data['displaygroup'] = $this->board->get_group_id($data['usergroupid'], array("not_multiple" => true));
		$insert_data['import_usergroup'] = $this->board->get_group_id($data['usergroupid'], array("original" => true));
		$insert_data['import_additionalgroups'] = $this->board->get_group_id($data['usergroupid'], array("original" => true));
		$insert_data['import_displaygroup'] = $data['displaygroupid'];
		$insert_data['import_uid'] = $data['userid'];
		$insert_data['username'] = encode_to_utf8($data['username'], "user", "users");
		$insert_data['email'] = $data['email'];
		$insert_data['regdate'] = $data['joindate'];
		$insert_data['lastactive'] = $data['lastactivity'];
		$insert_data['lastvisit'] = $data['lastvisit'];
		$insert_data['website'] = $data['homepage'];
		$avatar = $this->get_avatar($data['avatarid']);
		if(!$avatar)
		{
			$customavatar = $this->get_custom_avatar($data['userid']);
			if(!$customavatar)
			{
				$insert_data['avatardimensions'] = '';
				$insert_data['avatar'] = '';
				$insert_data['avatartype'] = '';
			}
		}
		else
		{
			list($width, $height) = @getimagesize($avatar['avatarpath']);
			$insert_data['avatardimensions'] = $width.'|'.$height;
			$insert_data['avatar'] = $avatar['avatarpath'];
			$insert_data['avatartype'] = 'remote';
		}
		$insert_data['lastpost'] = $data['lastpost'];
		$data['birthday'] = trim($data['birthday']);
		if(!empty($data['birthday']))
		{
			$insert_data['birthday'] = $data['birthday'];
		}
		$insert_data['icq'] = substr($data['icq'], 0, 10);
		$insert_data['aim'] = $data['aim'];
		$insert_data['yahoo'] = $data['yahoo'];
		$insert_data['timezone'] = str_replace(array('.0', '.00'), array('', ''), $insert_data['timezone']);
		$insert_data['style'] = 0;
		$insert_data['referrer'] = $data['referrerid'];
		$insert_data['regip'] = $data['ipaddress'];
		$insert_data['totalpms'] = $data['pmtotal'];
		$insert_data['unreadpms'] = $data['pmunread'];
		$insert_data['passwordconvert'] = $data['password'];
		$insert_data['passwordconverttype'] = 'vb3';
		$insert_data['passwordconvertsalt'] = $data['salt'];
		$insert_data['signature'] = encode_to_utf8($this->bbcode_parser->convert($this->get_signature($data['userid'])), "user", "users");

		return $insert_data;
	}

	/**
	 * Get a avatar from the vB database
	 *
	 * @param int Avatar ID
	 * @return array The avatar
	 */
	function get_avatar($aid)
	{
		$aid = intval($aid);
		$query = $this->old_db->simple_select("avatar", "*", "avatarid = '{$aid}'");
		$results = $this->old_db->fetch_array($query);
		$this->old_db->free_result($query);

		return $results;
	}

	/**
	 * Get a avatar from the vB database
	 *
	 * @param int Avatar ID
	 * @return array The avatar
	 */
	function get_custom_avatar($uid)
	{
		$uid = intval($uid);
		$query = $this->old_db->simple_select("customavatar", "*", "userid = '{$uid}'");
		return $this->old_db->fetch_array($query);
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