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

class VBULLETIN3_Converter_Module_Privatemessages extends Converter_Module_Privatemessages {

	var $settings = array(
		'friendly_name' => 'private messages',
		'progress_column' => 'pmid',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;

			$query = $this->old_db->query("
				SELECT *
				FROM ".OLD_TABLE_PREFIX."pm p
				LEFT JOIN ".OLD_TABLE_PREFIX."pmtext pt ON(p.pmtextid=pt.pmtextid)
				LIMIT ".$this->trackers['start_privatemessages'].", ".$import_session['privatemessages_per_screen']
			);
			while($pm = $this->old_db->fetch_array($query))
			{
				$this->insert($pm);
			}
	}

	function convert_data($data)
	{
		// vBulletin 3 values
		$insert_data['import_pmid'] = $data['pmid'];
		$insert_data['uid'] = $this->get_import->uid($data['userid']);
		$insert_data['fromid'] = $this->get_import->uid($data['fromuserid']);
		if($data['folderid'] == -1)
		{
			$insert_data['folder'] = PM_FOLDER_OUTBOX;
		}
		else
		{
			$insert_data['folder'] = PM_FOLDER_INBOX;
		}

		// Rebuild the recipients array and toid field
		$touserarray = unserialize($data['touserarray']);
		$recipients = array();

		// This is the original check in vB
		foreach($touserarray AS $key => $item)
		{
			if (is_array($item))
			{
				foreach($item AS $id => $name)
				{
					$recipients[$key][] = $this->get_import->uid($id);
				}
			}
			else
			{
				$recipients['bcc'][] = $this->get_import->uid($key);
			}
		}

		// However we use "to" instead of "cc"
		if(!empty($recipients['cc']))
		{
			$recipients['to'] = $recipients['cc'];
			unset($recipients['cc']);
		}
		
		$insert_data['recipients'] = serialize($recipients);

		// Now figure out what to do with toid
		if($insert_data['uid'] != $insert_data['fromid'])
		{
			// Inserting a pm for one of the recipients so the toid is our id
			$insert_data['toid'] = $insert_data['uid'];
		}
		elseif(count($recipients['to']) == 1)
		{
			// Inserting a pm for the sender with only one recipient so we can set the toid
			$insert_data['toid'] = $recipients['to'][0];
		}
		// Otherwise we're saving a pm with multiple recipients for the sender so the toid is "0" (default)

		$insert_data['subject'] = encode_to_utf8($data['title'], "pmtext", "privatemessages");
		if(strlen($insert_data['subject']) > 120)
		{
			$insert_data['subject'] = substr($insert_data['subject'], 0, 117)."...";
		}
		$insert_data['dateline'] = $data['dateline'];
		$insert_data['message'] = encode_to_utf8($this->bbcode_parser->convert($data['message']), "pmtext", "privatemessages");
		$insert_data['includesig'] = $data['showsignature'];
		$insert_data['smilieoff'] = int_to_01($data['allowsmilie']);

		if($data['messageread'] < 2)
		{
			// 0 and 1 are the same (unread, read)
			$insert_data['status'] = $data['messageread'];
		}
		else
		{
			// 2 and 3 are replied and forwarded but we use 3 and 4
			$insert_data['status'] = $data['messageread']+1;
			$insert_data['statustime'] = TIME_NOW;
		}

		if($data['messageread'] == PM_STATUS_READ)
		{
			$insert_data['readtime'] = TIME_NOW;
		}

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of private messages
		if(!isset($import_session['total_privatemessages']))
		{
			$query = $this->old_db->simple_select("pm", "COUNT(*) as count");
			$import_session['total_privatemessages'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_privatemessages'];
	}
}

?>
