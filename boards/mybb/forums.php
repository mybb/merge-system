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

class MYBB_Converter_Module_Forums extends Converter_Module_Forums  {

	var $settings = array(
		'friendly_name' => 'forums',
		'progress_column' => 'fid',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session, $db;

		$query = $this->old_db->simple_select("forums", "*", "", array('limit_start' => $this->trackers['start_forums'], 'limit' => $import_session['forums_per_screen'], 'order_by' => 'type', 'order_dir' => 'asc'));
		while($forum = $this->old_db->fetch_array($query))
		{
			$fid = $this->insert($forum);

			// Update parent list.
			if($insert_forum['type'] == 'c')
			{
				$db->update_query("forums", array('parentlist' => $fid), "import_fid = '{$forum['fid']}'");
			}
		}
	}

	function convert_data($data)
	{
		global $db;
		static $field_info;

		if(!isset($field_info))
		{
			// Get columns so we avoid any 'unknown column' errors
			$field_info = $db->show_fields_from("forums");
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
		$insert_data['import_fid'] = $data['fid'];
		$insert_data['import_pid'] = $data['pid'];
		$insert_data['description'] = encode_to_utf8($insert_data['description'], "forums", "forums");

		// This value NEEDS to be here for the cleanup() to work
		$insert_data['pid'] = 0;

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of forums
		if(!isset($import_session['total_forums']))
		{
			$query = $this->old_db->simple_select("forums", "COUNT(*) as count");
			$import_session['total_forums'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_forums'];
	}

	/**
	 * Correctly associate any forums with their correct parent ids. This is automagically run after importing forums.
	 */
	function cleanup()
	{
		global $db;

		$query = $db->query("
			SELECT f.fid, f.import_fid, f2.fid as updatefid
			FROM ".TABLE_PREFIX."forums f
			LEFT JOIN ".TABLE_PREFIX."forums f2 ON (f2.import_fid=f.import_pid)
			WHERE f.import_pid != '0' AND f.pid = '0'
		");
		while($forum = $db->fetch_array($query))
		{
			$db->update_query("forums", array('pid' => $forum['updatefid'], 'parentlist' => make_parent_list($forum['import_fid'])), "fid='{$forum['fid']}'", 1);
		}
	}
}

?>