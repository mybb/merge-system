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
			LEFT JOIN ".OLD_TABLE_PREFIX."conversation pm ON(pm.ConversationID=m.ConversationID)
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
		foreach($recip as $key => $id)
		{
			if($id == $data['InsertUserID'])
			{
				unset($recip[$key]);
				continue;
			}

			$recip[$key] = $this->get_import->uid($id);
			// This happens eg for the System user
			if($recip[$key] == 0)
			{
				unset($recip[$key]);
			}
		}

		$insert_data['import_pmid'] = $data['MessagegID'];
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
		$insert_data['readtime'] = TIME_NOW;
		$insert_data['dateline'] = strtotime($data['DateInserted']);
		$insert_data['message'] = encode_to_utf8($this->bbcode_parser->convert($data['Body']), "conversationmessage", "privatemessages");
		$insert_data['ipaddress'] = my_inet_pton($data['InsertIPAddress']);

		// Now save a copy for every user involved in this conversation
		// First the one who send this message (only if the user exists)
		if($insert_data['fromid'] != 0)
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
			$insert_data['status'] = 1; // Read - of course
			$insert_data['folder'] = 2; // Outbox
	
			$data = $this->prepare_insert_array($insert_data);
			unset($data['import_pmid']);
			$db->insert_query("privatemessages", $data);
		}

		foreach($recip as $key => $rec)
		{
			$insert_data['uid'] = $rec;
			$insert_data['toid'] = $rec;
			// It'd be too difficult to determine whether there was an answer so we simply set it to "read"
			$insert_data['status'] = 1;
			$insert_data['folder'] = 1; // Inbox

			// The last pm will be inserted by the main method, so we only insert x-1 here
			if($key < count($recip)-1)
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
			$query = $this->old_db->simple_select("conversationmessage", "COUNT(*) as count");
			$import_session['total_privatemessages'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_privatemessages'];
	}
}

?>