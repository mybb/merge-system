<?php
/**
 * MyBB 1.6
 * Copyright © 2009 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
  * License: http://www.mybb.com/about/license
 *
 * $Id: privatemessages.php 4394 2010-12-14 14:38:21Z ralgith $
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
					$insert_data[$field['Field']] = '';
				}
				continue;
			}

			if(isset($data[$field['Field']]))
			{
				$insert_data[$field['Field']] = $data[$field['Field']];
			}
		}
		
		// MyBB 1.6 values
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
	
	function test()
	{
		// import_uid => uid
		$this->get_import->cache_uids = array(
			5 => 10,
			6 => 11,
		);
		
		$data = array(
			'pmid' => 1,
			'uid' => 5,
			'fromid' => 5,
			'toid' => 6,
			'recipients' => serialize(array('to' => array(6))),
			'subject' => 'Testéfdfsÿÿ',
			'message' => 'Test, test, fdsfdsf ds dsf  estéfdf fdsfds sÿÿ'
		);
		
		$match_data = array(
			'import_pmid' => 1,
			'uid' => 10,
			'fromid' => 10,
			'toid' => 11,
			'recipients' => serialize(array('to' => array(11))),
			'subject' => utf8_encode('Testéfdfsÿÿ'), // The Merge System should convert the mixed ASCII/Unicode string to proper UTF8
			'message' => utf8_encode('Test, test, fdsfdsf ds dsf  estéfdf fdsfds sÿÿ')
		);
		
		$this->assert($data, $match_data);
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