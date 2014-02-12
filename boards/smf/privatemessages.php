<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 *
 * $Id: privatemessages.php 4394 2010-12-14 14:38:21Z ralgith $
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class SMF_Converter_Module_Privatemessages extends Converter_Module_Privatemessages {

	var $settings = array(
		'friendly_name' => 'private messages',
		'progress_column' => 'ID_PM',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;

		$query = $this->old_db->query("
			SELECT *
			FROM ".OLD_TABLE_PREFIX."personal_messages p
			LEFT JOIN ".OLD_TABLE_PREFIX."pm_recipients r ON(p.ID_PM=r.ID_PM)
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

		// SMF values
		$insert_data['pmid'] = null;
		$insert_data['import_pmid'] = $data['ID_PM'];
		$insert_data['uid'] = $this->get_import->uid($data['ID_MEMBER']);
		$insert_data['fromid'] = $this->get_import->uid($data['ID_MEMBER_FROM']);
		$insert_data['toid'] = $insert_data['uid'];
		$insert_data['recipients'] = serialize(array("to" => $insert_data['toid']));
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
			'ID_PM' => 1,
			'ID_MEMBER' => 5,
			'ID_MEMBER_FROM' => 6,
			'ID_MEMBER' => 5,
			'subject' => 'Testéfdfsÿÿ',
			'is_read' => 1,
			'msgtime' => 12345678,
			'body' => 'Test, test, fdsfdsf ds dsf  estéfdf fdsfds sÿÿ',
		);

		$match_data = array(
			'pmid' => null,
			'import_pmid' => 1,
			'uid' => 10,
			'fromid' => 11,
			'toid' => 10,
			'recipients' => serialize(array('to' => 10)),
			'folder' => 1,
			'subject' => utf8_encode('Testéfdfsÿÿ'), // The Merge System should convert the mixed ASCII/Unicode string to proper UTF8
			'status' => 1,
			'dateline' => 12345678,
			'message' => utf8_encode('Test, test, fdsfdsf ds dsf  estéfdf fdsfds sÿÿ'),
			'readtime' => TIME_NOW,
			'receipt' => '2',
		);

		$this->assert($data, $match_data);
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