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
		global $db;

		$insert_data = array();

		// phpBB 3 values
		$insert_data['fromid'] = $this->get_import->uid($data['author_id']);
		$insert_data['subject'] = encode_to_utf8(utf8_unhtmlentities($data['message_subject']), "privmsgs", "privatemessages");
		$insert_data['dateline'] = $data['message_time'];
		$insert_data['message'] = encode_to_utf8($this->bbcode_parser->convert($data['message_text'], $data['bbcode_uid']), "privmsgs", "privatemessages");
		$insert_data['includesig'] = $data['enable_sig'];
		$insert_data['smilieoff'] = int_to_01($data['enable_smilies']);
		$insert_data['ipaddress'] = my_inet_pton($data['author_ip']);

		// Now figure out who is participating
		$to = explode(':', $data['to_address']);
		foreach($to as $key => $uid)
		{
			if(empty($uid))
			{
				unset($to[$key]);
				continue;
			}
			$to[$key] = $this->get_import->uid(str_replace('u_', '', $uid));
		}

		$bcc = explode(':', $data['bcc_address']);
		foreach($bcc as $key => $uid)
		{
			if(empty($uid))
			{
				unset($bcc[$key]);
				continue;
			}
			$bcc[$key] = $this->get_import->uid(str_replace('u_', '', $uid));
		}

		$recipients = array();

		if(!empty($to))
		{
			$recipients['to'] = $to;
		}

		if(!empty($bcc))
		{
			$recipients['bcc'] = $bcc;
		}

		$insert_data['recipients'] = serialize($recipients);

		// Now save a copy for every user involved in this pm
		// First one for the sender - if he hasn't deleted it yet
		// Though I wasn't able to produce a scenario where "pm_deleted" is changed we exclude it to be one the safe site
		$tquery = $this->old_db->simple_select('privmsgs_to', 'msg_id', "msg_id={$data['msg_id']} AND user_id={$data['author_id']} AND pm_deleted=0");
		if($db->num_rows($tquery) == 1)
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

			$edata = $this->prepare_insert_array($insert_data);
			$db->insert_query("privatemessages", $edata);
		}

		// Though I wasn't able to produce a scenario where "pm_deleted" is changed we exclude it to be one the safe site
		$rec_query = $this->old_db->simple_select('privmsgs_to', '*', "msg_id={$data['msg_id']} AND user_id!={$data['author_id']} AND pm_deleted=0");
		$num = $this->old_db->num_rows($rec_query);
		$count = 0;
		while($rec = $this->old_db->fetch_array($rec_query))
		{
			$count++;

			$insert_data['uid'] = $this->get_import->uid($rec['user_id']);
			$insert_data['toid'] = $insert_data['uid'];

			$insert_data['status'] = PM_STATUS_UNREAD;
			if(!$rec['pm_unread'])
			{
				$insert_data['status'] = PM_STATUS_READ;
				$insert_data['readtime'] = TIME_NOW; // We don't have a real readtime as phpBB doesn't save that so we set it to now
			}
			if($rec['pm_replied'])
			{
				$insert_data['status'] = PM_STATUS_REPLIED;
				$insert_data['statustime'] = TIME_NOW;
			}
			$insert_data['folder'] = PM_FOLDER_INBOX;

			// The last pm will be inserted by the main method, so we only insert x-1 here
			if($count < $num)
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
			$query = $this->old_db->simple_select("privmsgs", "COUNT(*) as count");
			$import_session['total_privatemessages'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_privatemessages'];
	}
}

