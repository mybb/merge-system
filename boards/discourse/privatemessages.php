<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 *
 * Discourse Private Messages Converter
 * Discourse PMs are topics with archetype = 'private_message'.
 * Participants are tracked in topic_allowed_users and topic_allowed_groups.
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class DISCOURSE_Converter_Module_Privatemessages extends Converter_Module_Privatemessages {

	var $settings = array(
		'friendly_name' => 'private messages',
		'progress_column' => 'id',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;

		// Get all posts from private message topics
		// We get the first post (post_number=1) which contains the message body + title
		$query = $this->old_db->query("
			SELECT p.*, t.title, t.user_id as topic_creator_id
			FROM ".OLD_TABLE_PREFIX."posts p
			LEFT JOIN ".OLD_TABLE_PREFIX."topics t ON (t.id = p.topic_id)
			WHERE t.archetype = 'private_message' AND p.deleted_at IS NULL
			ORDER BY p.topic_id ASC, p.post_number ASC
			LIMIT ".$import_session['privatemessages_per_screen']." OFFSET ".$this->trackers['start_privatemessages']."
		");
		while($pm = $this->old_db->fetch_array($query))
		{
			$this->insert($pm);
		}
	}

	function convert_data($data)
	{
		global $db;

		$insert_data = array();

		$insert_data['fromid'] = $this->get_import->uid($data['user_id']);
		$insert_data['subject'] = $data['title'];
		$insert_data['dateline'] = strtotime($data['created_at']);

		if(!empty($data['cooked']))
		{
			$insert_data['message'] = $this->bbcode_parser->convert($data['cooked']);
		}
		else
		{
			$insert_data['message'] = $data['raw'];
		}

		// Get participants from topic_allowed_users
		$recipients = array();
		$rec_query = $this->old_db->simple_select("topic_allowed_users", "user_id", "topic_id = '{$data['topic_id']}'");
		while($rec = $this->old_db->fetch_array($rec_query))
		{
			$uid = $this->get_import->uid($rec['user_id']);
			if($uid && $uid != $insert_data['fromid'])
			{
				$recipients[] = $uid;
			}
		}
		$this->old_db->free_result($rec_query);

		$insert_data['recipients'] = serialize(array('to' => $recipients));

		// For each recipient, create an inbox copy (handled by parent insert via copy count)
		if(!empty($recipients))
		{
			$insert_data['toid'] = count($recipients) == 1 ? $recipients[0] : 0;

			// Create a copy for each recipient
			foreach($recipients as $key => $uid)
			{
				$insert_data['uid'] = $uid;
				$insert_data['folder'] = PM_FOLDER_INBOX;
				$insert_data['status'] = PM_STATUS_UNREAD;

				if($key < count($recipients) - 1)
				{
					$pm_data = $this->prepare_insert_array($insert_data, 'privatemessages');
					$db->insert_query("privatemessages", $pm_data);
				}
			}

			// Last one handled by parent insert
			return $insert_data;
		}

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		if(!isset($import_session['total_privatemessages']))
		{
			$query = $this->old_db->query("
				SELECT COUNT(*) as count
				FROM ".OLD_TABLE_PREFIX."posts p
				LEFT JOIN ".OLD_TABLE_PREFIX."topics t ON (t.id = p.topic_id)
				WHERE t.archetype = 'private_message' AND p.deleted_at IS NULL
			");
			$import_session['total_privatemessages'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_privatemessages'];
	}
}
