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

class MYBB_Converter_Module_Posts extends Converter_Module_Posts {

	var $settings = array(
		'friendly_name' => 'posts',
		'progress_column' => 'pid',
		'default_per_screen' => 1000,
		'check_table_type' => 'posts',
	);

	function import()
	{
		global $import_session, $db;

		$query = $this->old_db->simple_select("posts", "*", "", array('limit_start' => $this->trackers['start_posts'], 'limit' => $import_session['posts_per_screen']));
		while($post = $this->old_db->fetch_array($query))
		{
			$pid = $this->insert($post);

			// Restore firstpost connections
			$db->update_query("threads", array('firstpost' => $pid), "import_firstpost = '{$post['pid']}'");
		}
	}

	function convert_data($data)
	{
		global $db;
		static $field_info;

		if(!isset($field_info))
		{
			// Get columns so we avoid any 'unknown column' errors
			$field_info = $db->show_fields_from("posts");
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
		$insert_data['import_pid'] = $data['pid'];
		$insert_data['tid'] = $this->get_import->tid($data['tid']);
		$insert_data['fid'] = $this->get_import->fid($data['fid']);
		$insert_data['uid'] = $this->get_import->uid($data['uid']);
		$insert_data['username'] = $this->get_import->username($data['uid']);
		$insert_data['subject'] = encode_to_utf8($data['subject'], "posts", "posts");
		$insert_data['message'] = encode_to_utf8($data['message'], "posts", "posts");

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of posts
		if(!isset($import_session['total_posts']))
		{
			$query = $this->old_db->simple_select("posts", "COUNT(*) as count");
			$import_session['total_posts'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_posts'];
	}
}

?>