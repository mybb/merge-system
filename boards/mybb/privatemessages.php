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

class MYBB_Converter_Module_Privatemessages extends Converter_Module_Privatemessages {

	var $settings = array(
		'friendly_name' => 'private messages',
		'progress_column' => 'pmid',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select("privatemessages", "*", "", array('limit_start' => $this->trackers['start_privatemessages'], 'limit' => $import_session['privatemessages_per_screen']));
		while($pm = $this->old_db->fetch_array($query))
		{
			$this->insert($pm);
		}
	}

	function convert_data($data)
	{
		global $db;
		static $field_info;

		if(!isset($field_info))
		{
			// Get columns so we avoid any 'unknown column' errors
			$field_info = $db->show_fields_from("privatemessages");
		}

		$insert_data = array();

		foreach($field_info as $key => $field)
		{
			if($field['Extra'] == 'auto_increment')
			{
				if($db->type != "sqlite")
				{
					unset($insert_data[$field['Field']]);
				}
				continue;
			}

			if(isset($data[$field['Field']]))
			{
				$insert_data[$field['Field']] = $data[$field['Field']];
			}
		}

		// MyBB 1.8 values
		$insert_data['import_pmid'] = $data['pmid'];
		$insert_data['uid'] = $this->get_import->uid($data['uid']);
		$insert_data['fromid'] = $this->get_import->uid($data['fromid']);
		$insert_data['toid'] = $this->get_import->uid($data['toid']);
		$insert_data['subject'] = encode_to_utf8($data['subject'], "privatemessages", "privatemessages");
		$insert_data['message'] = encode_to_utf8($data['message'], "privatemessages", "privatemessages");

		$touserarray = unserialize($data['recipients']);

		// Rebuild the recipients array
		$recipients = array();
		if(is_array($touserarray['to']))
		{
			foreach($touserarray['to'] as $key => $uid)
			{
				$recipients['to'][] = $this->get_import->uid($uid);
			}
		}

		if(is_array($touserarray['bcc']))
		{
			foreach($touserarray['bcc'] as $key => $uid)
			{
				$recipients['bcc'][] = $this->get_import->uid($uid);
			}
		}
		$insert_data['recipients'] = serialize($recipients);

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of private messages
		if(!isset($import_session['total_privatemessages']))
		{
			$query = $this->old_db->simple_select("privatemessages", "COUNT(*) as count");
			$import_session['total_privatemessages'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_privatemessages'];
	}
}

?>