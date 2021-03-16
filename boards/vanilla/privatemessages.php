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

class VANILLA_Converter_Module_Privatemessages extends Converter_Module_Privatemessages {

	var $settings = array(
		'friendly_name' => 'private messages',
		'progress_column' => 'Message',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;

		$query = $this->old_db->query("SELECT m.*, pm.Subject, pm.Contributors AS recips
			FROM ".OLD_TABLE_PREFIX."conversationmessage m
			INNER JOIN ".OLD_TABLE_PREFIX."conversation pm ON(pm.ConversationID=m.ConversationID)
			WHERE pm.Contributors <> '' AND pm.Contributors IS NOT NULL
			LIMIT {$this->trackers['start_privatemessages']}, {$import_session['privatemessages_per_screen']}");
   		while($privatemessage = $this->old_db->fetch_array($query))
		{
			$this->insert($privatemessage);
		}
	}

	function convert_data($data)
	{
		global $db;

		$insert_data = array();

		// Vanilla values
		$recip = unserialize($data['recips']);
		$to_send = array();
		foreach($recip as $key => $id)
		{
			$recip[$key] = $this->get_import->uid($id);
			// This happens eg for the System user
			if($recip[$key] == 0)
			{
				unset($recip[$key]);
			}

			$query = $this->old_db->simple_select('userconversation', '*', "UserID={$id} AND ConversationID={$data['ConversationID']}");
			$rec = $this->old_db->fetch_array($query);
			if(!$rec['Deleted'])
			{
				$to_send[$recip[$key]] = $rec;
			}

			if($id == $data['InsertUserID'])
			{
				unset($recip[$key]);
				continue;
			}
		}

		$insert_data['fromid'] = $this->get_import->uid($data['InsertUserID']);
		$insert_data['recipients'] = serialize(array('to' => $recip));
		if(!empty($data['Subject']))
		{
			$insert_data['subject'] = encode_to_utf8($data['Subject'], "conversation", "privatemessages");
		}
		else
		{
			$insert_data['subject'] = "Vanilla imported conversation";
		}
		$insert_data['dateline'] = strtotime($data['DateInserted']);
		$insert_data['message'] = encode_to_utf8($this->bbcode_parser->convert($data['Body']), "conversationmessage", "privatemessages");
		$insert_data['ipaddress'] = my_inet_pton($data['InsertIPAddress']);

		// Now save a copy for every user involved in this conversation
		// First the one who send this message (only if the user exists and hasn't deleted it)
		if($insert_data['fromid'] != 0 && isset($to_send[$insert_data['fromid']]))
		{
			$insert_data['uid'] = $insert_data['fromid'];
			if(count($recip) == 1)
			{
				// We can't use [0] here as the key can be different so time for some array magic
				$keys = array_keys($recip);
				$insert_data['toid'] = $recip[$keys[0]];
			}
			else
			{
				$insert_data['toid'] = 0; // multiple recipients
			}
			$insert_data['status'] = PM_STATUS_READ; // Read - of course
			$insert_data['folder'] = PM_FOLDER_OUTBOX;

			if(count($to_send) > 1)
			{
				$data = $this->prepare_insert_array($insert_data, 'privatemessages');
				$db->insert_query("privatemessages", $data);
			}

			unset($to_send[$insert_data['fromid']]);
		}


		$key = 0;
		foreach($to_send as $uid => $rec)
		{
			$key++;

			$insert_data['uid'] = $uid;
			$insert_data['toid'] = $uid;

			$insert_data['status'] = PM_STATUS_UNREAD;
			if(strtotime($rec['DateLastViewed']) > $insert_data['dateline'])
			{
				$insert_data['status'] = PM_STATUS_READ;
				$insert_data['readtime'] = strtotime($rec['DateLastViewed']);
			}
			$insert_data['folder'] = PM_FOLDER_INBOX;

			// The last pm will be inserted by the main method, so we only insert x-1 here
			if($key < count($to_send))
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
			$query = $this->old_db->query("SELECT COUNT(*) AS count
			FROM ".OLD_TABLE_PREFIX."conversationmessage m
			INNER JOIN ".OLD_TABLE_PREFIX."conversation pm ON(pm.ConversationID=m.ConversationID)
			WHERE pm.Contributors <> '' AND pm.Contributors IS NOT NULL");
			$import_session['total_privatemessages'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_privatemessages'];
	}
}

