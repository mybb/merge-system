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
		$query = $this->old_db->query("
			SELECT u.*, f.signature
			FROM ".OLD_TABLE_PREFIX."user u
			LEFT JOIN ".OLD_TABLE_PREFIX."usertextfield f ON(f.userid=u.userid)
			ORDER BY u.userid asc
			LIMIT {$this->trackers['start_users']}, {$import_session['users_per_screen']}
		");
		while($user = $this->old_db->fetch_array($query))
		{
			$this->insert($user);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// vBulletin 3 values
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
		$insert_data['icq'] = $data['icq'];
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
		$insert_data['signature'] = encode_to_utf8($this->bbcode_parser->convert($data['signature']), "usertextfield", "users");

		return $insert_data;
	}

	/**
	 * Get a avatar from the vB database
	 *
	 * @param int $aid Avatar ID
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
	 * @param int $uid Avatar ID
	 * @return array The avatar
	 */
	function get_custom_avatar($uid)
	{
		$uid = intval($uid);
		$query = $this->old_db->simple_select("customavatar", "*", "userid = '{$uid}'");
		return $this->old_db->fetch_array($query);
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


