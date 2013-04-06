<?php
/**
 * MyBB 1.6
 * Copyright © 2009 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
  * License: http://www.mybb.com/about/license
 *
 * $Id: pollvotes.php 4394 2010-12-14 14:38:21Z ralgith $
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class SMF2_Converter_Module_Pollvotes extends Converter_Module_Pollvotes {

	var $settings = array(
		'friendly_name' => 'poll votes',
		'progress_column' => 'id_poll',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;
		
		$query = $this->old_db->simple_select("log_polls", "*", "", array('limit_start' => $this->trackers['start_pollvotes'], 'limit' => $import_session['pollvotes_per_screen']));
		while($pollvote = $this->old_db->fetch_array($query))
		{
			$this->insert($pollvote);
		}
	}
	
	function convert_data($data)
	{
		global $db;
		
		$insert_data = array();
		
		// SMF values
		$insert_data['uid'] = $this->get_import->uid($data['id_member']);
		$insert_data['dateline'] = TIME_NOW;
		$insert_data['voteoption'] = $data['id_choice'];
		$insert_data['pid'] = $this->get_import->pollid($data['id_poll']);
		
		return $insert_data;
	}
	
	function test()
	{
		// import_pollid => pollid
		$this->get_import->cache_pollids = array(
			2 => 10
		);
		
		// import_pollid => poll
		$this->get_import->cache_polls = array(
			2 => array(
				'dateline' => 12345678,
				'pid' => 3,
			),
		);
		
		// import_uid => uid
		$this->get_import->cache_uids = array(
			4 => 11
		);
		
		$data = array(
			'id_poll' => 2,
			'ID_MEMBER' => 4,
			'ID_CHOICE' => 1,
		);
		
		$match_data = array(
			'uid' => 11,
			'dateline' => 12345678,
			'voteoption' => 1,
			'pid' => 3,			
		);
		
		$this->assert($data, $match_data);
	}
	
	function fetch_total()
	{
		global $import_session;
		
		// Get number of poll votes
		if(!isset($import_session['total_pollvotes']))
		{
			$query = $this->old_db->simple_select("log_polls", "COUNT(*) as count");
			$import_session['total_pollvotes'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}
		
		return $import_session['total_pollvotes'];
	}
}

?>