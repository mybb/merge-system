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

class SMF_Converter_Module_Privatemessages extends Converter_Module_Privatemessages {

	var $settings = array(
		'friendly_name' => 'private messages',
		'progress_column' => 'ID_PM',
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
		$insert_data['fromid'] = $this->get_import->uid($data['ID_MEMBER_FROM']);
		$insert_data['subject'] = encode_to_utf8($data['subject'], "personal_messages", "privatemessages");
		$insert_data['dateline'] = $data['msgtime'];
		$insert_data['message'] = encode_to_utf8($this->bbcode_parser->convert(utf8_unhtmlentities($data['body'])), "personal_messages", "privatemessages");

		// Now figure out who is participating
		$to_send = $recipients = array();
		$rec_query = $this->old_db->simple_select('pm_recipients', '*', "ID_PM={$data['ID_PM']}");
		while($rec = $this->old_db->fetch_array($rec_query))
		{
			$rec['ID_MEMBER'] = $this->get_import->uid($rec['ID_MEMBER']);

			// We can't check it in the query above as the user was still a recipient in the first place
			if(!$rec['deleted'])
			{
				$to_send[] = $rec;
			}

			if($rec['bcc'])
			{
				$recipients['bcc'][] = $rec['ID_MEMBER'];
			}
			else
			{
				$recipients['to'][] = $rec['ID_MEMBER'];
			}
		}

		$insert_data['recipients'] = serialize($recipients);

		// Now save a copy for every user involved in this pm
		// First one for the sender - if not deleted
		if(!$data['deletedBySender'])
		{
			$insert_data['uid'] = $insert_data['fromid'];
			if (count($recipients['to']) == 1)
			{
				$insert_data['toid'] = $recipients['to'][0];
			}
			else
			{
				$insert_data['toid'] = 0; // multiple recipients
			}
			$insert_data['status'] = PM_STATUS_READ; // Read - of course
			$insert_data['readtime'] = 0;
			$insert_data['folder'] = PM_FOLDER_OUTBOX;

			if(count($to_send) > 0)
			{
				$edata = $this->prepare_insert_array($insert_data);
				$db->insert_query("privatemessages", $edata);
			}
		}

		foreach($to_send as $key => $rec)
		{
			$insert_data['uid'] = $rec['ID_MEMBER'];
			$insert_data['toid'] = $rec['ID_MEMBER'];

			$insert_data['status'] = PM_STATUS_UNREAD;
			if($rec['is_read'] == 1)
			{
				$insert_data['status'] = PM_STATUS_READ;
				$insert_data['readtime'] = TIME_NOW;
			}
			if($rec['is_read'] > 1)
			{
				$insert_data['status'] = PM_STATUS_REPLIED;
				$insert_data['statustime'] = TIME_NOW;
			}
			$insert_data['folder'] = PM_FOLDER_INBOX;

			// The last pm will be inserted by the main method, so we only insert x-1 here
			if($key < count($to_send)-1)
			{
				$data = $this->prepare_insert_array($insert_data);
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

