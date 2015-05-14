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

class IPB4_Converter_Module_Privatemessages extends Converter_Module_Privatemessages {

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
			FROM ".OLD_TABLE_PREFIX."core_message_posts m
			LEFT JOIN ".OLD_TABLE_PREFIX."core_message_topics mt ON(m.msg_topic_id=mt.mt_id)
			LEFT JOIN ".OLD_TABLE_PREFIX."core_message_topic_user_map mp ON(mt.mt_id=mp.map_topic_id AND mp.map_user_id=mt.mt_starter_id)
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

		// Invision Power Board 4 values
		$insert_data['import_pmid'] = $data['msg_id'];
		$insert_data['fromid'] = $this->get_import->uid($data['msg_author_id']);
		$insert_data['subject'] = $data['mt_title'];
		// For some reason PMs are surrounded by new lines. Trimming them may break a few PMs but better than all
		$insert_data['message'] = trim($this->bbcode_parser->convert($data['msg_post']));
		$insert_data['ipaddress'] = my_inet_pton($data['msg_ip_address']);
		$insert_data['dateline'] = $data['msg_date'];

		// Now figure out who is participating
		$recipients = $to_send = array();
		$rec_query = $this->old_db->simple_select('core_message_topic_user_map', '*', "map_topic_id={$data['mt_id']} AND map_user_id!={$data['msg_author_id']}");
		while ($rec = $this->old_db->fetch_array($rec_query))
		{
			$rec['map_user_id'] = $this->get_import->uid($rec['map_user_id']);

			// Only get active users - active means delete here so if the user isn't active he deleted the conversation
			// Also active is set to "0" for "ignored/blocked/banned" users
			if($rec['map_user_active'] != 0)
			{
				$to_send[] = $rec;
			}

			$recipients[] = $rec['map_user_id'];
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
			$insert_data['folder'] = PM_FOLDER_OUTBOX;

			if(count($to_send) > 0)
			{
				$data = $this->prepare_insert_array($insert_data);
				unset($data['import_pmid']);
				$db->insert_query("privatemessages", $data);
			}
		}

		foreach($to_send as $key => $rec)
		{
			$insert_data['uid'] = $rec['map_user_id'];
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
			$query = $this->old_db->simple_select("core_message_posts", "COUNT(*) as count");
			$import_session['total_privatemessages'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_privatemessages'];
	}
}

?>
