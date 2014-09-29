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

class IPB3_Converter_Module_Users extends Converter_Module_Users {

	var $settings = array(
		'friendly_name' => "users",
		'progress_column' => "member_id",
		'encode_table' => "members",
		'postnum_column' => "posts",
		'username_column' => 'name',
		'email_column' => 'email',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;

		// Get members
		$query = $this->old_db->query("
			SELECT *
			FROM ".OLD_TABLE_PREFIX."members m
			LEFT JOIN ".OLD_TABLE_PREFIX."profile_portal pp ON (m.member_id=pp.pp_member_id)
			LEFT JOIN ".OLD_TABLE_PREFIX."pfields_content pc ON (m.member_id=pc.member_id)
			LIMIT ".$this->trackers['start_users'].", ".$import_session['users_per_screen']
		);
		while($user = $this->old_db->fetch_array($query))
		{
			$this->insert($user);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// Invision Power Board 3 values
		$insert_data['usergroup'] = $this->board->get_group_id($data['member_group_id'], array("not_multiple" => true));
		$insert_data['additionalgroups'] = $this->board->get_group_id($data['mgroup_others']);
		$insert_data['displaygroup'] = $insert_data['usergroup'];
		$insert_data['import_usergroup'] = $data['member_group_id'];
		$insert_data['import_additionalgroups'] = $this->board->get_group_id($data['mgroup_others'], array("original" => true));
		$insert_data['import_displaygroup'] = $data['member_group_id'];
		$insert_data['import_uid'] = $data['member_id'];
		$insert_data['username'] = encode_to_utf8($data['name'], "members", "users");
		$insert_data['email'] = $data['email'];
		$insert_data['regdate'] = $data['joined'];
		$insert_data['lastactive'] = $data['last_activity'];
		$insert_data['lastvisit'] = $data['last_visit'];
		$insert_data['website'] = $data['field_3'];
		$insert_data['lastpost'] = $data['last_post'];
		$data['bday_day'] = trim($data['bday_day']);
		$data['bday_month'] = trim($data['bday_month']);
		$data['bday_year'] = trim($data['bday_year']);
		if(!empty($data['bday_day']) && !empty($data['bday_month']) && !empty($data['bday_year']))
		{
			$insert_data['birthday'] = $data['bday_day'].'-'.$data['bday_month'].'-'.$data['bday_year'];
		}
		$insert_data['icq'] = $data['field_4'];
		$insert_data['aim'] = $data['field_1'];
		$insert_data['yahoo'] = $data['field_8'];
		$insert_data['skype'] = $data['field_10'];
		$insert_data['timezone'] = str_replace(array('.0', '.00'), array('', ''), $data['time_offset']);
		$insert_data['timezone'] = ((!strstr($insert_data['timezone'], '+') && !strstr($insert_data['timezone'], '-')) ? '+'.$insert_data['timezone'] : $insert_data['timezone']);
		$insert_data['style'] = 0;
		$insert_data['regip'] = my_inet_pton($data['ip_address']);
		$insert_data['totalpms'] = $data['msg_count_total'];
		$insert_data['unreadpms'] = $data['msg_count_new'];
		$insert_data['dst'] = $data['dst_in_use'];
		$insert_data['signature'] =  encode_to_utf8($this->bbcode_parser->convert($data['pp_bio_content ']), "profile_portal", "users");
		$insert_data['passwordconvertsalt'] = $data['members_pass_salt'];
		$insert_data['passwordconvert'] = $data['members_pass_hash'];
		$insert_data['passwordconverttype'] = 'ipb3';

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of members
		if(!isset($import_session['total_users']))
		{
			$query = $this->old_db->simple_select("members", "COUNT(*) as count");
			$import_session['total_users'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_users'];
	}
}

?>