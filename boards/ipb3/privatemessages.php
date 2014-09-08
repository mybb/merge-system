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

class IPB3_Converter_Module_Privatemessages extends Converter_Module_Privatemessages {

	var $settings = array(
		'friendly_name' => 'private messages',
		'progress_column' => 'msg_id',
	);

	function import()
	{
		global $import_session;

		$query = $this->old_db->query("
			SELECT *
			FROM ".OLD_TABLE_PREFIX."message_posts m
			LEFT JOIN ".OLD_TABLE_PREFIX."message_topics mt ON(m.msg_topic_id=mt.mt_id)
			LEFT JOIN ".OLD_TABLE_PREFIX."message_topic_user_map mp ON(mt.mt_id=mp.map_topic_id)
			LIMIT ".$this->trackers['start_privatemessages'].", ".$import_session['privatemessages_per_screen']
		);
		while($pm = $this->old_db->fetch_array($query))
		{
			$this->insert($pm);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// Invision Power Board 3 values
		$insert_data['import_pmid'] = $data['msg_id'];
		$insert_data['uid'] = $this->get_import->uid($data['msg_author_id']);
		$insert_data['fromid'] = $this->get_import->uid($data['mt_starter_id']);
		$insert_data['toid'] = $this->get_import->uid($data['mt_to_member_id']);
		/*$touserarray = explode('<br />', $data['msg_cc_users']);

		// Rebuild the recipients array
		$recipients = array();
		foreach($touserarray as $key => $to)
		{
			$username = $this->get_username($to);
			$recipients['to'][] = $this->get_import->username($username['id']);
		}
		$insert_data['recipients'] = serialize($recipients);*/

		if($data['map_folder_id'] == 'myconvo' && $data['map_is_starter'] == '0')
		{
			$insert_data['folder'] = 2;
		}
		elseif($data['map_folder_id'] == 'drafts')
		{
			$insert_data['folder'] = 3;
		}
		else
		{
			$insert_data['folder'] = 1;
		}

		$insert_data['subject'] = encode_to_utf8($data['mt_title'], "message_posts", "privatemessages");
		$insert_data['status'] = $data['mt_read'];
		$insert_data['dateline'] = $data['mt_date'];
		$insert_data['message'] = encode_to_utf8($this->bbcode_parser->convert(utf8_unhtmlentities($data['msg_post'])), "message_posts", "privatemessages");
		$insert_data['readtime'] = $data['map_read_time'];
		$insert_data['ipaddress'] = my_inet_pton($data['msg_ip_address']);

		return $insert_data;
	}

	/**
	 * Get a user from the IPB database
	 *
	 * @param int Username
	 * @return array If the username is empty, returns an array of username as Guest.  Otherwise returns the user
	 */
	function get_username($username)
	{
		if($username == '')
		{
			return array(
				'username' => 'Guest',
				'id' => 0,
			);
		}

		$query = $this->old_db->simple_select("members", "*", "name='{$username}'", array('limit' => 1));

		$results = $this->old_db->fetch_array($query);
		$this->old_db->free_result($query);

		return $results;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of private messages
		if(!isset($import_session['total_privatemessages']))
		{
			$query = $this->old_db->simple_select("message_posts", "COUNT(*) as count");
			$import_session['total_privatemessages'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_privatemessages'];
	}
}

?>