<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 *
 * $Id: users.php 4394 2010-12-14 14:38:21Z ralgith $
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class PHPBB2_Converter_Module_Users extends Converter_Module_Users {

	var $settings = array(
		'friendly_name' => 'users',
		'progress_column' => 'user_id',
		'encode_table' => 'users',
		'postnum_column' => 'user_posts',
		'username_column' => 'username',
		'email_column' => 'user_email',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;

		// Get members
		$query = $this->old_db->simple_select("users", "*", "user_id > 0", array('limit_start' => $this->trackers['start_users'], 'limit' => $import_session['users_per_screen']));
		while($user = $this->old_db->fetch_array($query))
		{
			$this->insert($user);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// phpBB 2 values
		$insert_data['usergroup'] = $this->board->get_group_id($data, array("not_multiple" => true));
		$insert_data['additionalgroups'] = str_replace($insert_data['usergroup'], '', $this->board->get_group_id($data));
		$insert_data['displaygroup'] = $this->board->get_group_id($data, array("not_multiple" => true));
		$insert_data['import_usergroup'] = $this->board->get_group_id($data, array("not_multiple" => true, "original" => true));
		$insert_data['import_additionalgroups'] = $this->board->get_group_id($data, array("original" => true));
		$insert_data['import_displaygroup'] = $data['group_id'];
		$insert_data['import_uid'] = $data['user_id'];
		$insert_data['username'] = encode_to_utf8($data['username'], "users", "users");
		$insert_data['email'] = $data['user_email'];
		$insert_data['regdate'] = $data['user_regdate'];
		$insert_data['lastactive'] = $data['user_lastvisit'];
		$insert_data['lastvisit'] = $data['user_lastvisit'];
		$insert_data['website'] = $data['user_website'];
		$insert_data['avatar'] = $data['user_avatar'];
		list($width, $height) = @getimagesize($data['user_avatar']);
		$insert_data['avatardimensions'] = $width.'|'.$height;
		if($insert_data['avatar'] == '')
		{
			$insert_data['avatartype'] = "";
		}
		else
		{
			$insert_data['avatartype'] = 'remote';
		}
		$last_post = $this->get_last_post($data['user_id']);
		$insert_data['lastpost'] = intval($last_post['post_time']);
		$insert_data['icq'] = $data['user_icq'];
		$insert_data['aim'] = $data['user_aim'];
		$insert_data['yahoo'] = $data['user_yim'];
		$insert_data['msn'] = $data['user_msnm'];
		$insert_data['hideemail'] = $data['hideEmail'];
		$insert_data['invisible'] = int_to_01($data['user_allow_viewonline']);
		$insert_datar['allownotices'] = $data['user_notify'];
		if($data['user_notify'] == 1)
		{
			$subscription_method == 2;
		}
		else
		{
			$subscription_method = 0;
		}
		$insert_data['subscriptionmethod'] = $subscription_method;
		$insert_data['receivepms'] = $data['user_allow_pm'];
		$insert_data['pmnotice'] = $data['user_popup_pm'];
		$insert_data['pmnotify'] = $data['pm_email_notify'];
		$insert_data['showsigs'] = $data['user_attachsig'];
		$insert_data['showavatars'] = $data['user_allowavatar'];
		$insert_data['timeformat'] = $data['user_dateformat'];
		$insert_data['timezone'] = $data['user_timezone'];
		$insert_data['regip'] = my_inet_pton($last_post['poster_ip']);
		$insert_data['totalpms'] = $this->get_private_messages($data['user_id']);
		$insert_data['unreadpms'] = $data['user_unread_privmsg'];
		$insert_data['salt'] = generate_salt();
		$insert_data['signature'] = encode_to_utf8(str_replace(':'.$data['user_sig_bbcode_uid'], '', utf8_unhtmlentities($data['user_sig'])), "users", "users");
		$insert_data['password'] = salt_password($data['user_password'], $insert_data['salt']);
		$insert_data['loginkey'] = generate_loginkey();

		return $insert_data;
	}

	/**
	 * Get total number of Private Messages the user has from the phpBB database
	 *
	 * @param int User ID
	 * @return int Number of Private Messages
	 */
	 function get_private_messages($uid)
	 {
	 	$query = $this->old_db->simple_select("privmsgs", "COUNT(*) as pms", "privmsgs_to_userid = '$uid' OR privmsgs_from_userid = '$uid'");

		$results = $this->old_db->fetch_field($query, 'pms');
		$this->old_db->free_result($query);

		return $results;
	 }

	 /**
	 * Gets the time of the last post of a user from the phpBB database
	 *
	 * @param int User ID
	 * @return int Last post time
	 */
	function get_last_post($uid)
	{
		$query = $this->old_db->simple_select("posts", "post_time, poster_id", "poster_id='{$uid}'", array('order_by' => 'post_time', 'order_dir' => 'ASC', 'limit' => 1));
		$results = $this->old_db->fetch_array($query);
		$this->old_db->free_result($query);

		return $results;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of members
		if(!isset($import_session['total_users']))
		{
			$query = $this->old_db->simple_select("users", "COUNT(*) as count", "user_id > 0");
			$import_session['total_users'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_users'];
	}
}

?>