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

class SMF2_Converter_Module_Privatemessages extends Converter_Module_Privatemessages {

	var $settings = array(
		'friendly_name' => 'private messages',
		'progress_column' => 'id_pm',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;

		$query = $this->old_db->query("
			SELECT *
			FROM ".OLD_TABLE_PREFIX."personal_messages p
			LIMIT ".$this->trackers['start_privatemessages'].", ".$import_session['privatemessages_per_screen']
		);
		while($privatemessage = $this->old_db->fetch_array($query))
		{
			$this->insert($privatemessage);
		}
	}

	function convert_data($data)
	{
		global $db;

		$insert_data = array();

		// SMF values
		$insert_data['import_pmid'] = $data['id_pm'];
		$insert_data['fromid'] = $this->get_import->uid($data['id_member_from']);
		$insert_data['subject'] = encode_to_utf8($data['subject'], "personal_messages", "privatemessages");
		$insert_data['dateline'] = $data['msgtime'];
		$insert_data['message'] = encode_to_utf8($this->bbcode_parser->convert(utf8_unhtmlentities($data['body'])), "personal_messages", "privatemessages");

		// Now figure out who is participating
		$to_send = $recipients = array();
		$rec_query = $this->old_db->simple_select('pm_recipients', '*', "id_pm={$data['id_pm']} AND id_member!={$data['id_member_from']}");
		while($rec = $this->old_db->fetch_array($rec_query))
		{
			$rec['id_member'] = $this->get_import->uid($rec['id_member']);
			$to_send[] = $rec;
			if($rec['bcc'])
			{
				$recipients['bcc'][] = $rec['id_member'];
			}
			else
			{
				$recipients['to'][] = $rec['id_member'];
			}
		}

		$insert_data['recipients'] = serialize($recipients);

		// Now save a copy for every user involved in this pm
		// First one for the sender
		$insert_data['uid'] = $insert_data['fromid'];
		if(count($recipients['to']) == 1)
		{
			$insert_data['toid'] = $recipients['to'][0];
		}
		else
		{
			$insert_data['toid'] = 0; // multiple recipients
		}
		$insert_data['status'] = 1; // Read - of course
		$insert_data['readtime'] = 0;
		$insert_data['folder'] = 2; // Outbox

		$edata = $this->prepare_insert_array($insert_data);
		unset($edata['import_pmid']);
		$db->insert_query("privatemessages", $edata);

		foreach($to_send as $key => $rec)
		{
			$insert_data['uid'] = $rec['id_member'];
			$insert_data['toid'] = $rec['id_member'];
			// 0 -> unread
			// 1 -> read
			$insert_data['status'] = 0;
			if($data['is_read'] > 0)
			{
				$insert_data['status'] = 1;
			}
			if($insert_data['status'] == 1)
			{
				$insert_data['readtime'] = TIME_NOW;
			}
			$insert_data['folder'] = 1; // Inbox

			// The last pm will be inserted by the main method, so we only insert x-1 here
			if($key < count($to_send)-1)
			{
				$data = $this->prepare_insert_array($insert_data);
				unset($data['import_pmid']);
				$db->insert_query("privatemessages", $data);
			}
		}

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of private messages
		if(!isset($import_session['total_privatemessages']))
		{
			$query = $this->old_db->simple_select("personal_messages", "COUNT(*) as count");
			$import_session['total_privatemessages'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_privatemessages'];
	}
}

?>