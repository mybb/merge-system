<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2019 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class XENFORO2_Converter_Module_Attachments extends Converter_Module_Attachments
{
	var $settings = array(
		'friendly_name' => 'attachments',
		'progress_column' => 'attachment_id',
		'default_per_screen' => 20,
	);

	public $path_column = "attachment_id, data_id";

	public $test_table = "attachment";

	function get_upload_path()
	{
		$query = $this->old_db->simple_select("option", "option_value", "option_id='boardUrl'");
		$uploadspath = $this->old_db->fetch_field($query, "option_value") . "/internal_data/attachments/";
		$this->old_db->free_result($query);
		return $uploadspath;
	}

	function import()
	{
		global $import_session;

		$query = $this->old_db->query("SELECT *
				FROM ".OLD_TABLE_PREFIX."attachment a
				LEFT JOIN ".OLD_TABLE_PREFIX."attachment_data d ON (d.data_id=a.data_id)
				WHERE a.content_type='post'
				LIMIT {$this->trackers['start_attachments']}, {$import_session['attachments_per_screen']}");
		while($attachment = $this->old_db->fetch_array($query))
		{
			$this->insert($attachment);
		}
	}

	function convert_data($data)
	{
		global $db;

		$insert_data = array();

		// Xenforo 2 values
		$insert_data['import_aid'] = $data['attachment_id'];

		$ext = get_extension($data['filename']);
		$query = $db->simple_select("attachtypes", "mimetype", "extension='{$ext}'");
		$insert_data['filetype'] = $db->fetch_field($query, "mimetype");
		$db->free_result($query);

		// Check if it is it an image
		switch(strtolower($insert_data['filetype']))
		{
			case "image/gif":
			case "image/jpeg":
			case "image/x-jpg":
			case "image/x-jpeg":
			case "image/pjpeg":
			case "image/jpg":
			case "image/png":
			case "image/x-png":
				$is_image = 1;
				break;
			default:
				$is_image = 0;
				break;
		}

		// Should have thumbnail if it's an image
		if($is_image == 1)
		{
			$insert_data['thumbnail'] = 'SMALL';
		}
		else
		{
			$insert_data['thumbnail'] = '';
		}

		$insert_data['uid'] = $this->get_import->uid($data['user_id']);
		$insert_data['filename'] = $data['filename'];
		$insert_data['filesize'] = $data['file_size'];
		$insert_data['downloads'] = $data['view_count'];

		$attach_details = $this->get_import->post_attachment_details($data['content_id']);

		$insert_data['pid'] = $attach_details['pid'];
		$insert_data['posthash'] = md5($attach_details['tid'].$attach_details['uid'].random_str());		

		// Build name and check whether it's already in use
		$insert_data['attachname'] = "post_".$insert_data['uid']."_".$data['attach_date'].".attach";
		$query = $db->simple_select("attachments", "aid", "attachname='".$db->escape_string($insert_data['attachname'])."'");
		if($db->num_rows($query) > 0)
		{
			$insert_data['attachname'] = "post_".$insert_data['uid']."_".$data['attach_date']."_".$data['attachment_id'].".attach";
		}

		return $insert_data;
	}

	function generate_raw_filename($attach)
	{
		if(!isset($attach['file_hash']))
		{
			$query = $this->old_db->simple_select("attachment_data", "file_hash", "data_id='{$attach['data_id']}'");
			$data = $this->old_db->fetch_array($query);
			$this->old_db->free_result($query);
			$attach = array_merge($attach, $data);
		}
		$name = floor($attach['data_id']/1000)."/{$attach['data_id']}-{$attach['file_hash']}.data";

		return $name;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of attachments
		if(!isset($import_session['total_attachments']))
		{
			$query = $this->old_db->simple_select("attachment", "COUNT(*) as count", "content_type='post'");
			$import_session['total_attachments'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_attachments'];
	}
}


