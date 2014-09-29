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

class PHPBB3_Converter_Module_Privatemessages extends Converter_Module_Privatemessages {

	var $settings = array(
		'friendly_name' => 'private messages',
		'progress_column' => 'msg_id',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select("privmsgs", "*", "", array('limit_start' => $this->trackers['start_privatemessages'], 'limit' => $import_session['privatemessages_per_screen']));
		while($privatemessage = $this->old_db->fetch_array($query))
		{
			$this->insert($privatemessage);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// phpBB 3 values
		$to = explode(':', $data['to_address']);

		foreach($to as $key => $uid)
		{
			$to[$key] = $this->get_import->uid(str_replace('u_', '', $uid));
		}
		$toid = $to[0];

		$insert_data['import_pmid'] = $data['msg_id'];
		$insert_data['uid'] = $toid;
		$insert_data['fromid'] = $this->get_import->uid($data['author_id']);
		$insert_data['toid'] = $toid;
		$insert_data['recipients'] = serialize(array('to' => $to));
		$text = encode_to_utf8($data['message_subject'], "privmsgs", "privatemessages");
		$text = preg_replace('/&quot;/','"', $text);
		$text = preg_replace('/&lt;/','<', $text);
		$text = preg_replace('/&gt;/','>', $text);
		$text = preg_replace('/&amp;/','&', $text);
		$insert_data['subject'] = $text;
		$insert_data['status'] = $this->get_pm_status($data['msg_id']);
		$insert_data['readtime'] = TIME_NOW;
		$insert_data['dateline'] = $data['message_time'];
		$insert_data['message'] = encode_to_utf8($this->bbcode_parser->convert($data['message_text'], $data['bbcode_uid']), "privmsgs", "privatemessages");
		$insert_data['includesig'] = $data['enable_sig'];
		$insert_data['smilieoff'] = int_to_01($data['enable_smilies']);
		$insert_data['ipaddress'] = my_inet_pton($data['author_ip']);

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of private messages
		if(!isset($import_session['total_privatemessages']))
		{
			$query = $this->old_db->simple_select("privmsgs", "COUNT(*) as count");
			$import_session['total_privatemessages'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_privatemessages'];
	}

	function get_pm_status($pm_id)
	{
		$query = $this->old_db->simple_select("privmsgs_to", "pm_unread", "msg_id = {$pm_id}");
		$retval = $this->old_db->fetch_field($query, "pm_unread");
		$this->old_db->free_result($query);
		return $retval;
	}
}

?>