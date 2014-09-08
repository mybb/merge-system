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

class SMF2_Converter_Module_Privatemessages extends Converter_Module_Privatemessages {

	var $settings = array(
		'friendly_name' => 'private messages',
		'progress_column' => 'id_pm',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;

		$query = $this->old_db->query("
			SELECT *
			FROM ".OLD_TABLE_PREFIX."personal_messages p
			LEFT JOIN ".OLD_TABLE_PREFIX."pm_recipients r ON(p.id_pm=r.id_pm)
			LIMIT ".$this->trackers['start_privatemessages'].", ".$import_session['privatemessages_per_screen']
		);
		while($privatemessage = $this->old_db->fetch_array($query))
		{
			$this->insert($privatemessage);
		}
	}

	function convert_data($data)
	{
		global $db;

		$insert_data = array();

		$query = $this->old_db->simple_select("pm_recipients", "*", "id_pm = '{$data['id_pm']}'");
		$sep = '';
		while($recip = $this->old_db->fetch_field($query, 'id_member'))
		{
			$recip_list .= $sep.$recip;
			$sep = ',';
		}
		$this->old_db->free_result($query);
		$recip_list = explode(",", $recip_list);

		// SMF values
		$insert_data['pmid'] = null;
		$insert_data['import_pmid'] = $data['id_pm'];
		$insert_data['uid'] = $this->get_import->uid($data['id_member']);
		$insert_data['fromid'] = $this->get_import->uid($data['id_member_from']);
		$insert_data['toid'] = $this->get_import->uid($recip_list['0']);
		$insert_data['recipients'] = serialize($recip_list);
		$insert_data['folder'] = '1';
		$insert_data['subject'] = encode_to_utf8($data['subject'], "personal_messages", "privatemessages");
		$insert_data['status'] = $data['is_read'];
		$insert_data['dateline'] = $data['msgtime'];
		$insert_data['message'] = encode_to_utf8($this->bbcode_parser->convert(utf8_unhtmlentities($data['body'])), "personal_messages", "privatemessages");
		if($insert_data['status'] == '1')
		{
			$insert_data['readtime'] = TIME_NOW;
			$insert_data['receipt'] = '2';
		}

		// Hack to work around SMF 2's way of storing multiple recipients in the db...
		// NOT a very efficient way to handle this, but it works for now.
		$this->insert_extra_pms($recip_list, $insert_data);

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
			'id_pm' => 1,
			'id_member' => 5,
			'id_member_from' => 6,
			'id_member' => 5,
			'subject' => 'Test�fdfs��',
			'is_read' => 1,
			'msgtime' => 12345678,
			'body' => 'Test, test, fdsfdsf ds dsf  est�fdf fdsfds s��',
		);

		$match_data = array(
			'pmid' => null,
			'import_pmid' => 1,
			'uid' => 10,
			'fromid' => 11,
			'toid' => 10,
			'recipients' => serialize(array('to' => 10)),
			'folder' => 1,
			'subject' => utf8_encode('Test�fdfs��'), // The Merge System should convert the mixed ASCII/Unicode string to proper UTF8
			'status' => 1,
			'dateline' => 12345678,
			'message' => utf8_encode('Test, test, fdsfdsf ds dsf  est�fdf fdsfds s��'),
			'readtime' => TIME_NOW,
			'receipt' => '2',
		);

		$this->assert($data, $match_data);
	}

	function insert_extra_pms($recip_list, $data)
	{
		global $db;

		$this->debug->log->datatrace('$data', $data);

		foreach($recip_list as $pos => $val)
		{
			if($pos == '0')
			{
				continue;
			}

			$data['toid'] = $this->get_import->uid($val);

			// Should loop through and fill in any values that aren't set based on the MyBB db schema or other standard default values and escape them properly
			$insert_array = $this->prepare_insert_array($data);

			$this->debug->log->datatrace('$insert_array', $insert_array);

			$db->insert_query("privatemessages", $insert_array);
		}
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of private messages
		if(!isset($import_session['total_privatemessages']))
		{
			$query = $this->old_db->simple_select("personal_messages", "COUNT(*) as count");
			$import_session['total_privatemessages'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_privatemessages'];
	}
}

?>