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

class WBB3_Converter_Module_Privatemessages extends Converter_Module_Privatemessages {

	var $settings = array(
		'friendly_name' => 'private messages',
		'progress_column' => 'pmID',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select(WCF_PREFIX."pm", "*", "isDraft='0'", array('limit_start' => $this->trackers['start_privatemessages'], 'limit' => $import_session['privatemessages_per_screen']));
		while($privatemessage = $this->old_db->fetch_array($query))
		{
			$this->insert($privatemessage);
		}
	}

	function convert_data($data)
	{
		global $db;

		$insert_data = array();

		// WBB 3 values
		$insert_data['fromid'] = $this->get_import->uid($data['userID']);
		$insert_data['subject'] = encode_to_utf8($data['subject'], WCF_PREFIX."pm", "privatemessages");
		$insert_data['dateline'] = $data['time'];
		$insert_data['message'] = encode_to_utf8($this->bbcode_parser->convert($data['message']), WCF_PREFIX."pm", "privatemessages");
		$insert_data['includesig'] = $data['showSignature'];
		$insert_data['smilieoff'] = int_to_01($data['enableSmilies']);

		// Now build our recipients list
		$rec_query = $this->old_db->simple_select(WCF_PREFIX."pm_to_user", "*", "pmID='{$data['pmID']}'");
		$to_send = $recipients = array();
		while($rec = $this->old_db->fetch_array($rec_query))
		{
			$rec['recipientID'] = $this->get_import->uid($rec['recipientID']);
			$to_send[] = $rec;
			if($rec['isBlindCopy'])
			{
				$recipients['bcc'][] = $rec['recipientID'];
			}
			else
			{
				$recipients['to'][] = $rec['recipientID'];
			}
		}

		$insert_data['recipients'] = serialize($recipients);

		// Now save a copy for every user involved in this pm
		// First one for the sender - if he wants it
		if($data['saveInOutbox'])
		{
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

			$data = $this->prepare_insert_array($insert_data);
			unset($data['import_pmid']);
			$db->insert_query("privatemessages", $data);
		}

		foreach($to_send as $key => $rec)
		{
			$insert_data['uid'] = $rec['recipientID'];
			$insert_data['toid'] = $rec['recipientID'];
			// 0 -> unread
			// 1 -> read
			// 3 -> answered
			// 4 -> forwared
			$insert_data['status'] = 0;
			if($rec['isViewed'] > 0)
			{
				$insert_data['status'] = 1;
			}
			if($rec['isReplied'])
			{
				$insert_data['status'] = 3;
			}
			if($rec['isForwared'])
			{
				$insert_data['status'] = 4;
			}
			$insert_data['readtime'] = $rec['isViewed'];
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
			$query = $this->old_db->simple_select(WCF_PREFIX."pm", "COUNT(*) as count", "isDraft='0'");
			$import_session['total_privatemessages'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_privatemessages'];
	}
}

?>