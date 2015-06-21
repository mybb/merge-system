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

class XENFORO_Converter_Module_Privatemessages extends Converter_Module_Privatemessages {

	var $settings = array(
		'friendly_name' => 'private messages',
		'progress_column' => 'message_id',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;
		
		$query = $this->old_db->query("
			SELECT m.*, c.title, r.recipient_state
			FROM ".OLD_TABLE_PREFIX."conversation_message m
			LEFT JOIN ".OLD_TABLE_PREFIX."conversation_master c ON(c.conversation_id=m.conversation_id)
			LEFT JOIN ".OLD_TABLE_PREFIX."conversation_recipient r ON(r.conversation_id=m.conversation_id AND r.user_id=m.user_id)
			LEFT JOIN ".OLD_TABLE_PREFIX."ip i ON(i.ip_id=m.ip_id)
			LIMIT ".$this->trackers['start_privatemessages'].", ".$import_session['privatemessages_per_screen']
		);			
		while($pm = $this->old_db->fetch_array($query))
		{
			$this->insert($pm);
		}
	}
	
	function convert_data($data)
	{
		global $db;
		
		// Xenforo 1 values
		$insert_data['fromid'] = $this->get_import->uid($data['user_id']);
		$insert_data['subject'] = encode_to_utf8($data['title'], "conversation_master", "privatemessages");
		$insert_data['dateline'] = $data['message_date'];
		$insert_data['message'] = encode_to_utf8($this->bbcode_parser->convert($data['message']), "conversation_message", "privatemessages");
		$insert_data['ipaddress'] = $data['ip'];

		// Now build our recipients list
		$rec_query = $this->old_db->simple_select("conversation_recipient", "*", "conversation_id='{$data['conversation_id']}' AND user_id!='{$data['user_id']}'");
		$to_send = $recipients = array();
		while($rec = $this->old_db->fetch_array($rec_query))
		{
			$rec['user_id'] = $this->get_import->uid($rec['user_id']);

			// "deleted_ignored" means he left the conversation and doesn't want to receive new messages
			if($rec['recipient_status'] != 'deleted_ignored')
			{
				$to_send[] = $rec;
			}

			$recipients['to'][] = $rec['user_id'];
		}

		$insert_data['recipients'] = serialize($recipients);

		// Now save a copy for every user involved in this pm
		// First one for the sender - if he hasn't deleted it
		if($data['recipient_status'] != 'deleted_ignored')
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

			if($data['recipient_state'] == 'deleted')
			{
				$insert_data['folder'] = PM_FOLDER_TRASH;
			}
			else
			{
				$insert_data['folder'] = PM_FOLDER_OUTBOX;
			}

			if(count($to_send) > 0)
			{
				$data = $this->prepare_insert_array($insert_data, 'privatemessages');
				$db->insert_query("privatemessages", $data);
			}
		}

		foreach($to_send as $key => $rec)
		{
			$insert_data['uid'] = $rec['user_id'];
			$insert_data['toid'] = $rec['user_id'];

			$insert_data['status'] = PM_STATUS_UNREAD;
			if($rec['last_read_date'] > $insert_data['dateline'])
			{
				$insert_data['status'] = PM_STATUS_READ;
			}
			$insert_data['readtime'] = $rec['last_read_date'];

			if($rec['recipient_state'] == 'deleted')
			{
				$insert_data['folder'] = PM_FOLDER_TRASH;
			}
			else
			{
				$insert_data['folder'] = PM_FOLDER_INBOX;
			}

			// The last pm will be inserted by the main method, so we only insert x-1 here
			if($key < count($to_send)-1)
			{
				$data = $this->prepare_insert_array($insert_data, 'privatemessages');
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
			$query = $this->old_db->simple_select("conversation_message", "COUNT(*) as count");
			$import_session['total_privatemessages'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}
	}
}
