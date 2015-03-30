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

class VBULLETIN4_Converter_Module_Privatemessages extends Converter_Module_Privatemessages {

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
		// vBulletin 4 values
		$insert_data['import_pmid'] = $data['pmid'];
		$insert_data['uid'] = $this->get_import->uid($data['userid']);
		$insert_data['fromid'] = $this->get_import->uid($data['fromuserid']);
		if($data['folderid'] == -1)
		{
			$insert_data['folder'] = 2;
		}
		else
		{
			$insert_data['folder'] = 1;
		}

		// Rebuild the recipients array and toid field
		$touserarray = unserialize($data['touserarray']);
		$recipients = array();
		// main recipients are in cc array
		if(is_array($touserarray['cc']))
		{
			foreach($touserarray['cc'] as $id => $name)
			{
				$recipients['to'][] = $this->get_import->uid($id);
			}
		}

		// import bcc, too
		if(is_array($touserarray['bcc']) && !empty($touserarray['bcc']))
		{
			foreach($touserarray['bcc'] as $id => $name)
			{
				$recipients['bcc'][] = $this->get_import->uid($id);
			}
		}
		$insert_data['recipients'] = serialize($recipients);

		// set toid if there is only one recipient
		if(count($recipients['to']) == 1)
		{
			$insert_data['toid'] = $recipients['to'][0];
		}

		$insert_data['subject'] = encode_to_utf8($data['title'], "pmtext", "privatemessages");
		$insert_data['status'] = $data['messageread'];
		$insert_data['dateline'] = $data['dateline'];
		$insert_data['message'] = encode_to_utf8($this->bbcode_parser->convert($data['message']), "pmtext", "privatemessages");
		$insert_data['includesig'] = $data['showsignature'];
		$insert_data['smilieoff'] = int_to_01($data['allowsmilie']);

		if($data['messageread'] == 1)
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