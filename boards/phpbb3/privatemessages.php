<?php
/**
 * MyBB 1.6
 * Copyright © 2009 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
  * License: http://www.mybb.com/about/license
 *
 * $Id: privatemessages.php 4396 2010-12-14 20:02:15Z ralgith $
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class PHPBB3_Converter_Module_Privatemessages extends Converter_Module_Privatemessages {

	var $settings = array(
		'friendly_name' => 'private messages',
		'progress_column' => 'msg_id',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;
		
		$query = $this->old_db->simple_select("privmsgs", "*", "", array('limit_start' => $this->trackers['start_privatemessages'], 'limit' => $import_session['privatemessages_per_screen']));
		while($privatemessage = $this->old_db->fetch_array($query))
		{
			$this->insert($privatemessage);
		}
	}
	
	function convert_data($data)
	{
		$insert_data = array();
		
		// phpBB 3 values
		$to = explode(':', $data['to_address']);
		
		foreach($to as $key => $uid)
		{
			$to[$key] = $this->get_import->uid(str_replace('u_', '', $uid));
		}
		$toid = $to[0];
		
		$insert_data['import_pmid'] = $data['msg_id'];
		$insert_data['uid'] = $toid;
		$insert_data['fromid'] = $this->get_import->uid($data['author_id']);
		$insert_data['toid'] = $toid;
		$insert_data['recipients'] = serialize(array('to' => $to));
		$insert_data['subject'] = encode_to_utf8($data['message_subject'], "privmsgs", "privatemessages");
		$insert_data['status'] = $this->get_pm_status($data['msg_id']);
		$insert_data['readtime'] = TIME_NOW;
		$insert_data['dateline'] = $data['message_time'];
		$insert_data['message'] = encode_to_utf8($this->bbcode_parser->convert($data['message_text'], $data['bbcode_uid']), "privmsgs", "privatemessages");
		$insert_data['includesig'] = $data['enable_sig'];
		$insert_data['smilieoff'] = int_to_01($data['enable_smilies']);
		
		return $insert_data;
	}
	
	function test()
	{
		// import_uid => uid
		$this->get_import->cache_uids = array(
			5 => 10,
			6 => 11,
			7 => 12,
		);
		
		$data = array(
			'msg_id' => 1,
			'to_address' => 'u_5:u_6',
			'author_id' => 7,
			'message_subject' => 'Testéfdfsÿÿ',
			'message_time' => 12345678,
			'message_text' => 'Test, test, fdsfdsf ds dsf  estéfdf fdsfds sÿÿ',
			'bbcode_uid' => 5,
			'enable_sig' => 1,
			'enable_smilies' => 1,
		);
		
		$match_data = array(
			'import_pmid' => 1,
			'uid' => 10,
			'fromid' => 12,
			'toid' => 10,
			'recipients' => serialize(array('to' => array(10, 11))),
			'subject' => utf8_encode('Testéfdfsÿÿ'), // The Merge System should convert the mixed ASCII/Unicode string to proper UTF8
			'readtime' => TIME_NOW,
			'dateline' => 12345678,
			'message' => utf8_encode('Test, test, fdsfdsf ds dsf  estéfdf fdsfds sÿÿ'),
			'readtime' => TIME_NOW,
			'includesig' => 1,
			'smilieoff' => 0,
		);
		
		$this->assert($data, $match_data);
	}
	
	function fetch_total()
	{
		global $import_session;
		
		// Get number of private messages
		if(!isset($import_session['total_privatemessages']))
		{
			$query = $this->old_db->simple_select("privmsgs", "COUNT(*) as count");
			$import_session['total_privatemessages'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}
		
		return $import_session['total_privatemessages'];
	}

	function get_pm_status($pm_id)
	{	
		$query = $this->old_db->simple_select("privmsgs_to", "pm_unread", "msg_id = {$pm_id}");
		$retval = $this->old_db->fetch_field($query, "pm_unread");
		$this->old_db->free_result($query);
		return $retval;
	}
}

?>