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

class MYBB_Converter_Module_Attachments extends Converter_Module_Attachments {

	var $settings = array(
		'friendly_name' => 'attachments',
		'progress_column' => 'aid',
		'default_per_screen' => 20,
	);

	public $path_column = "attachname";

	function get_upload_path()
	{
		$query = $this->old_db->simple_select("settings", "value", "name = 'bburl'", array('limit' => 1));
		$bburl = $this->old_db->fetch_field($query, 'value');
		$this->old_db->free_result($query);

		$query = $this->old_db->simple_select("settings", "value", "name = 'uploadspath'", array('limit' => 1));
		$uploadspath = str_replace('./', $bburl.'/', $this->old_db->fetch_field($query, 'value'));
		$this->old_db->free_result($query);

		return $uploadspath;
	}

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select("attachments", "*", "", array('limit_start' => $this->trackers['start_attachments'], 'limit' => $import_session['attachments_per_screen']));
		while($attachment = $this->old_db->fetch_array($query))
		{
			$this->insert($attachment);
		}
	}

	function convert_data($data)
	{
		global $db;
		/** @var array $field_info */
		static $field_info;

		if(!isset($field_info))
		{
			// Get columns so we avoid any 'unknown column' errors
			$field_info = $db->show_fields_from("attachments");
		}

		$insert_data = array();

		foreach($field_info as $key => $field)
		{
			if($field['Extra'] == 'auto_increment')
			{
				unset($insert_data[$field['Field']]);
				continue;
			}

			if(isset($data[$field['Field']]))
			{
				$insert_data[$field['Field']] = $data[$field['Field']];
			}
		}

		// MyBB 1.8 values
		$insert_data['import_aid'] = $data['aid'];
		$insert_data['pid'] = $this->get_import->pid($data['pid']);
		$insert_data['uid'] = $this->get_import->uid($data['uid']);

		$attachname_array = explode('_', str_replace('.attach', '', $data['attachname']));
		$insert_data['attachname'] = 'post_'.$this->get_import->uid($attachname_array[1]).'_'.$attachname_array[2].'.attach';

		if($data['thumbnail'])
		{
			$ext = get_extension($data['thumbnail']);
			$insert_data['thumbnail'] = str_replace(".attach", "_thumb.$ext", $insert_data['attachname']);
		}

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of attachments
		if(!isset($import_session['total_attachments']))
		{
			$query = $this->old_db->simple_select("attachments", "COUNT(*) as count");
			$import_session['total_attachments'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_attachments'];
	}
}


