<?php
/**
 * MyBB 1.6
 * Copyright 2009 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class MYBB_Converter_Module_Threads extends Converter_Module_Threads {

	var $settings = array(
		'friendly_name' => 'threads',
		'progress_column' => 'tid',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select("threads", "*", "", array('limit_start' => $this->trackers['start_threads'], 'limit' => $import_session['threads_per_screen']));
		while($thread = $this->old_db->fetch_array($query))
		{
			$this->insert($thread);
		}
	}

	function convert_data($data)
	{
		global $db;
		static $field_info;

		if(!isset($field_info))
		{
			// Get columns so we avoid any 'unknown column' errors
			$field_info = $db->show_fields_from("threads");
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
		$insert_data['import_tid'] = $data['tid'];
		$insert_data['fid'] = $this->get_import->fid($data['fid']);
		$insert_data['uid'] = $this->get_import->uid($data['uid']);
		$insert_data['import_firstpost'] = $data['firstpost'];
		$insert_data['subject'] = encode_to_utf8($data['subject'], "threads", "threads");

		return $insert_data;
	}

	function test()
	{
		// import_fid -> fid
		$this->get_import->cache_fids = array(
			5 => 10,
		);

		// import_uid -> uid
		$this->get_import->cache_uids = array(
			6 => 11,
		);

		$data = array(
			'tid' => 4,
			'fid' => 5,
			'uid' => 6,
			'firstpost' => 7,
			'subject' => 'Testéfdfsÿÿ subject',
			'poll' => 8,
		);

		$match_data = array(
			'import_tid' => 4,
			'fid' => 10,
			'uid' => 11,
			'firstpost' => -7,
			'subject' => utf8_encode('Testéfdfsÿÿ subject'), // The Merge System should convert the mixed ASCII/Unicode string to proper UTF8
			'poll' => -8,
		);

		$this->assert($data, $match_data);
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of threads
		if(!isset($import_session['total_threads']))
		{
			$query = $this->old_db->simple_select("threads", "COUNT(*) as count");
			$import_session['total_threads'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_threads'];
	}
}

?>