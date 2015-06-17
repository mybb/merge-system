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

class WBB4_Converter_Module_Privatemessages extends Converter_Module_Privatemessages {

	var $settings = array(
		'friendly_name' => 'private messages',
		'progress_column' => 'pmID',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;

		$query = $this->old_db->query("SELECT m.*, c.subject, c.isDraft, c.draftData
			FROM ".WCF_PREFIX."conversation_message m
			LEFT JOIN ".WCF_PREFIX."conversation c ON(c.conversationID=m.conversationID)
			LIMIT ".$this->trackers['start_privatemessages'].", ".$import_session['privatemessages_per_screen']);
		while($privatemessage = $this->old_db->fetch_array($query))
		{
			$this->insert($privatemessage);
		}
	}

	function convert_data($data)
	{
		global $db;

		$insert_data = array();

		// WBB 4 values
		$insert_data['fromid'] = $this->get_import->uid($data['userID']);
		$insert_data['subject'] = encode_to_utf8($data['subject'], WCF_PREFIX."conversation", "privatemessages");
		$insert_data['dateline'] = $data['time'];
		$insert_data['message'] = encode_to_utf8($this->bbcode_parser->convert($data['message']), WCF_PREFIX."conversation_message", "privatemessages");
		$insert_data['includesig'] = $data['showSignature'];
		$insert_data['smilieoff'] = int_to_01($data['enableSmilies']);

		// Now build our recipients list
		$to_send = $recipients = array();
		if($data['isDraft'])
		{
			// Draft handles the recipients in a special array so figure them out
			$draftData = unserialize($data['draftData']);
			foreach($draftData['participants'] as $uid)
			{
				$recipients['to'][] = $this->get_import->uid($uid);
			}

			foreach($draftData['invisibleParticipants'] as $uid)
			{
				$recipients['bcc'][] = $this->get_import->uid($uid);
			}
		}
		else
		{
			// Otherwise we have real recipients which we need to query
			$rec_query = $this->old_db->simple_select(WCF_PREFIX . "conversation_to_user", "*", "conversationID='{$data['conversationID']}' AND participantID!='{$data['userID']}'");
			while ($rec = $this->old_db->fetch_array($rec_query))
			{
				$rec['participantID'] = $this->get_import->uid($rec['participantID']);

				// 2 means that the user left the conversation
				if($rec['hideConversation'] < 2)
				{
					$to_send[] = $rec;
				}

				if ($rec['isInvisible'])
				{
					$recipients['bcc'][] = $rec['participantID'];
				}
				else
				{
					$recipients['to'][] = $rec['participantID'];
				}
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
		$insert_data['status'] = PM_STATUS_READ; // Read - of course
		$insert_data['readtime'] = 0;

		if($data['isDraft'])
		{
			$insert_data['folder'] = PM_FOLDER_DRAFTS;
		}
		else
		{
			$insert_data['folder'] = PM_FOLDER_OUTBOX;
		}

		if(count($to_send) > 0)
		{
			$data = $this->prepare_insert_array($insert_data);
			$db->insert_query("privatemessages", $data);
		}

		foreach($to_send as $key => $rec)
		{
			$insert_data['uid'] = $rec['participantID'];
			$insert_data['toid'] = $rec['participantID'];

			$insert_data['status'] = PM_STATUS_UNREAD;
			if($rec['lastVisitTime'] > $insert_data['dateline'])
			{
				$insert_data['status'] = PM_STATUS_READ;
			}
			$insert_data['readtime'] = $rec['lastVisitTime'];

			if($rec['hideConversation'])
			{
				// A hidden Conversation is not really trash but it shouldn't be shown in the inbox
				// The user decided that he doesn't want to see messages and the trash is the most similar we have
				$insert_data['folder'] = PM_FOLDER_TRASH;
			}
			else
			{
				$insert_data['folder'] = PM_FOLDER_INBOX;
			}

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
			$query = $this->old_db->simple_select(WCF_PREFIX."conversation_message", "COUNT(*) as count");
			$import_session['total_privatemessages'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_privatemessages'];
	}
}

