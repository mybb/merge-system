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
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;

		$query = $this->old_db->query("
			SELECT *
			FROM ".OLD_TABLE_PREFIX."message_posts m
			LEFT JOIN ".OLD_TABLE_PREFIX."message_topics mt ON(m.msg_topic_id=mt.mt_id)
			LEFT JOIN ".OLD_TABLE_PREFIX."message_topic_user_map mp ON(mp.map_topic_id=mt.mt_id AND mp.map_user_id=mt.mt_starter_id)
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

		$insert_data = array();

		// Invision Power Board 3 values
		$insert_data['fromid'] = $this->get_import->uid($data['msg_author_id']);
		$insert_data['subject'] = encode_to_utf8($data['mt_title'], "message_topics", "privatemessages");
		$insert_data['message'] = encode_to_utf8($this->bbcode_parser->convert(utf8_unhtmlentities($data['msg_post'])), "message_posts", "privatemessages");
		$insert_data['ipaddress'] = my_inet_pton($data['msg_ip_address']);
		$insert_data['dateline'] = $data['msg_date'];

		// Now figure out who is participating
		$recipients = unserialize($data['mt_invited_members']);
		$recipients[] = $data['mt_to_member_id'];

		foreach($recipients as $k => $rec)
		{
			$recipients[$k] = $this->get_import->uid($rec);
		}

		$insert_data['recipients'] = serialize(array('to' => $recipients));

		// Now save a copy for every user involved in this pm
		// First one for the sender - if he hasn't deleted the conversation
		if($data['map_user_active'])
		{
			$insert_data['uid'] = $insert_data['fromid'];
			if (count($recipients) == 1)
			{
				$insert_data['toid'] = $recipients[0];
			}
			else
			{
				$insert_data['toid'] = 0; // multiple recipients
			}
			$insert_data['status'] = PM_STATUS_READ; // Read - of course
			$insert_data['readtime'] = 0;


			// Now a bit of magic: we need to return one insert array as our parent method inserts one
			// If we save a draft we only have one so we need to return here
			// Otherwise we need to insert multiple pms and so we need to insert it manually
			if ($data['map_folder_id'] == 'drafts')
			{
				$insert_data['folder'] = PM_FOLDER_DRAFTS;
				return $insert_data;
			}
			else
			{
				$insert_data['folder'] = PM_FOLDER_OUTBOX;
			}
		}

		$edata = $this->prepare_insert_array($insert_data, 'privatemessages');
		$db->insert_query("privatemessages", $edata);

		// Some more magic: get the map data for every recip and insert all except the last - we need the data for it but don't insert!
		// Only get active users - active means delete here so if the user isn't active he deleted the conversation
		// Also active is set to "0" for "ignored/blocked/banned" users
		$rec_query = $this->old_db->simple_select('message_topic_user_map', '*', "map_topic_id={$data['mt_id']} AND map_user_id!={$data['msg_author_id']} AND map_user_active=1");
		$num = $this->old_db->num_rows($rec_query);
		$count = 0;
		while($rec = $this->old_db->fetch_array($rec_query))
		{
			$count++;

			$insert_data['uid'] = $this->get_import->uid($rec['map_user_id']);
			$insert_data['toid'] = $insert_data['uid'];

			$insert_data['status'] = PM_STATUS_UNREAD;
			// The "map_read_time" is set when first opening the message
			// However it's possible to mark as unread where only "map_has_unread" is updated and not "map_read_time"
			if($rec['map_read_time'] > $insert_data['dateline'] && !$rec['map_has_unread'])
			{
				$insert_data['status'] = PM_STATUS_READ;
			}
			$insert_data['readtime'] = $rec['map_read_time'];
			$insert_data['folder'] = PM_FOLDER_INBOX;

			// The last pm will be inserted by the main method, so we only insert x-1 here
			if($count < $num)
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
			$query = $this->old_db->simple_select("message_posts", "COUNT(*) as count");
			$import_session['total_privatemessages'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_privatemessages'];
	}
}

