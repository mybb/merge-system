<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2019 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class DZUCENTER_Converter_Module_Privatemessages extends Converter_Module_Privatemessages {
	
	var $settings = array(
			'friendly_name' => 'private messages',
			'progress_column' => 'pmid',
			'default_per_screen' => 1000,
	);
	
	function import()
	{
		global $import_session;
		
		$query = $this->old_db->query("
			SELECT
				pidx.*,
				plist.authorid AS conversation_fuid,
				plist.pmtype AS pmtype,
				plist.subject AS subject,
				plist.members AS conversation_members,
				plist.min_max AS conversation_uids,
				plist.dateline AS dateline,
				plist.lastmessage AS lastmessage
			FROM ".OLD_TABLE_PREFIX."pm_indexes AS pidx
				LEFT JOIN ".OLD_TABLE_PREFIX."pm_lists AS plist
					ON (plist.plid = pidx.plid)
			ORDER BY pidx.pmid ASC
			LIMIT ".$this->trackers['start_privatemessages'].", ".$import_session['privatemessages_per_screen']
				);
		while($privatemessage = $this->old_db->fetch_array($query))
		{
			$pm_messages_tableid = intval($privatemessage['plid']) % 10;
			$pm_message = $this->dzucenter_get_pm_message($privatemessage['pmid'], $pm_messages_tableid);
			
			if(empty($pm_message))
			{
				// Only message index exists, skip this record.
				$this->increment_tracker('privatemessages');
				continue;
			}
			
			$this->insert($privatemessage);
		}
	}
	
	function convert_data($data)
	{
		global $db, $import_session;
		
		$insert_data = array();
		
		// Discuz! X2.5 values.
		$pm_messages_tableid = intval($data['plid']) % 10;
		$pm_members = $this->dzucenter_get_pm_members($data['plid']);
		$pm_message = $this->dzucenter_get_pm_message($data['pmid'], $pm_messages_tableid);
		
		$insert_data['message'] = $this->board->encode_to_utf8($pm_message['message'], "pm_messages_".$pm_messages_tableid, "privatemessages");
		$insert_data['message'] = $this->bbcode_parser->convert_post($insert_data['message'], $import_session['encode_to_utf8'] ? 'utf-8' : $this->board->fetch_table_encoding("pm_messages_".$pm_messages_tableid));
		if(!empty($data['subject']))
		{
			$insert_data['subject'] = $this->board->encode_to_utf8($data['subject'], "pm_lists", "privatemessages");
		}
		else
		{
			$insert_data['subject'] = my_substr($insert_data['message'], 0, 20) . (my_strlen($insert_data['message']) > 20 ? ' ...' : '');
		}
		$insert_data['dateline'] = $pm_message['dateline'];
		
		// Now figure out who is participating.
		// TODO: 2 users at most?
		$conversation_fuid = $data['conversation_fuid'];
		$uids = explode("_", trim($data['conversation_uids']));
		$from_uid = trim($uids[0]);
		$to_uid = trim($uids[1]);
		if($pm_message['authorid'] == $from_uid)
		{
			$from_uid = trim($uids[0]);
			$to_uid = trim($uids[1]);
		}
		else if($pm_message['authorid'] == $to_uid)
		{
			$from_uid = trim($uids[1]);
			$to_uid = trim($uids[0]);
		}
		else
		{
			$this->board->set_error_notice_in_progress("import_privatemessages: Discuz! UCenter database may be corrupted, PM participating users are wrong. An incorrect record would be inserted.");
			return $insert_data;
		}
		$insert_data['fromid'] = $this->get_import->uid($from_uid);
		$insert_data['toid'] = $this->get_import->uid($to_uid);
		$recipients = array('to' => array(intval($insert_data['toid'])));
		$insert_data['recipients'] = serialize($recipients);
		
		$sender_has = false;
		$receiver_has = false;
		
		if($pm_message['delstatus'] == 0)
		{
			$sender_has = true;
			$receiver_has = true;
		}
		else if($pm_message['delstatus'] == 1)
		{
			$sender_has = true;
			$receiver_has = true;
			// $conversation_fuid has deleted the message.
			if($conversation_fuid == $from_uid)
			{
				$sender_has = false;
			}
			if($conversation_fuid == $to_uid)
			{
				$receiver_has = false;
			}
		}
		else if($pm_message['delstatus'] == 2)
		{
			// The other side has deleted the message.
			if($conversation_fuid != $from_uid)
			{
				$sender_has = true;
			}
			if($conversation_fuid != $to_uid)
			{
				$receiver_has = true;
			}
		}
		
		if(!$sender_has && !$receiver_has)
		{
			$this->board->set_error_notice_in_progress("import_privatemessages: Discuz! UCenter database may be corrupted, PM deleted statuses are wrong. An incorrect record would be inserted.");
			return $insert_data;
		}
		
		$insert_data_array = array();
		if($sender_has)
		{
			$insert_data['uid'] = $this->get_import->uid($from_uid);
			$insert_data['folder'] = PM_FOLDER_OUTBOX;
			$insert_data['status'] = PM_STATUS_READ;
			$insert_data['readtime'] = 0;
			// Stop tracking.
			$insert_data['receipt'] = 0;
			
			$insert_data_array[] = $insert_data;
		}
		if($receiver_has)
		{
			// Overwrite $sendser_has has defined.
			$insert_data['uid'] = $this->get_import->uid($to_uid);
			$insert_data['folder'] = PM_FOLDER_INBOX;
			$insert_data['status'] = PM_STATUS_UNREAD;
			$insert_data['readtime'] = 0;
			
			$the_time_max = max($pm_members[$to_uid]['lastupdate'], $pm_members[$to_uid]['lastdateline']);
			$the_time_min = min($pm_members[$to_uid]['lastupdate'], $pm_members[$to_uid]['lastdateline']);
			
			if($insert_data['dateline'] < $the_time_max)
			{
				$insert_data['status'] = PM_STATUS_READ;
				if($insert_data['dateline'] < $the_time_min)
				{
					$insert_data['readtime'] = $the_time_min;
				}
				else
				{
					$insert_data['readtime'] = $the_time_max;
				}
			}
			
			// Check again. Maybe it's an old notification-like PM.
			if($insert_data['status'] == PM_STATUS_UNREAD && $pm_members[$to_uid]['isnew'] == 0)
			{
				$insert_data['status'] = PM_STATUS_READ;
				$insert_data['readtime'] = $insert_data['dateline'];
			}
			
			// Set receipt bit.
			if($insert_data['status'] == PM_STATUS_UNREAD)
			{
				$insert_data['receipt'] = 1;
			}
			else if($insert_data['status'] == PM_STATUS_READ)
			{
				$insert_data['receipt'] = 2;
			}
			
			$insert_data_array[] = $insert_data;
		}
		
		foreach($insert_data_array as $key => $rec)
		{
			// The last pm will be inserted by the main method, so we only insert x-1 here
			if($key < count($insert_data_array)-1)
			{
				$data = $this->prepare_insert_array($rec, 'privatemessages');
				$db->insert_query("privatemessages", $data);
			}
		}
		
		if(count($insert_data_array))
		{
			$insert_data = $insert_data_array[count($insert_data_array)-1];
		}
		else
		{
			// If no more data to import, return an empty array. This marks an error.
			$insert_data = array();
		}

		return $insert_data;
	}
	
	function dzucenter_get_pm_members($plid)
	{
		$result = array();
		$rec_query = $this->old_db->simple_select('pm_members', '*', "plid={$plid}");
		while($rec = $this->old_db->fetch_array($rec_query))
		{
			$result[$rec['uid']] = $rec;
		}
		$this->old_db->free_result($rec_query);
		return $result;
	}
	
	function dzucenter_get_pm_message($pmid, $pmm_tableid)
	{
		$rec_query = $this->old_db->simple_select('pm_messages_'.$pmm_tableid, '*', "pmid='{$pmid}'");
		$result = $this->old_db->fetch_array($rec_query);
		$this->old_db->free_result($rec_query);
		return $result;
	}
	
	function fetch_total()
	{
		global $import_session;
		
		// Get number of private messages
		if(!isset($import_session['total_privatemessages']))
		{
			$query = $this->old_db->simple_select("pm_indexes", "COUNT(*) as count");
			$import_session['total_privatemessages'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}
		
		return $import_session['total_privatemessages'];
	}
}

