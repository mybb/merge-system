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

class IPB3_Converter_Module_Attachments extends Converter_Module_Attachments {

	var $settings = array(
		'friendly_name' => "attachments",
		'progress_column' => "attach_id",
		'default_per_screen' => 20,
	);

	public $path_column = "attach_location";

	public function get_upload_path()
	{
		$query = $this->old_db->simple_select("core_sys_conf_settings", "conf_value", "conf_key = 'upload_url'", array('limit' => 1));
		$uploadspath = $this->old_db->fetch_field($query, 'conf_value');
		$this->old_db->free_result($query);
		return $uploadspath;
	}

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select("attachments", "*", "attach_rel_module='post'", array('limit_start' => $this->trackers['start_attachments'], 'limit' => $import_session['attachments_per_screen']));
		while($attachment = $this->old_db->fetch_array($query))
		{
			$this->insert($attachment);
		}
	}

	function convert_data($data)
	{
		global $db, $error_notice;

		$error_notice = "";

		$insert_data = array();

		// Invision Power Board 3 values
		$insert_data['import_aid'] = $data['attach_id'];

		$attach_details = $this->get_import->post_attachment_details($data['attach_rel_id']);
		$insert_data['pid'] = $attach_details['pid'];
		$insert_data['posthash'] = md5($attach_details['tid'].$attach_details['uid'].random_str());

		$insert_data['filetype'] = $this->get_attach_type($data['attach_ext']);

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

		// should have thumbnail if it's an image
		if($is_image == 1)
		{
			$insert_data['thumbnail'] = 'SMALL';
		}
		else
		{
			$insert_data['thumbnail'] = '';
		}

		$insert_data['uid'] = $this->get_import->uid($data['attach_member_id']);
		$insert_data['filename'] = $data['attach_file'];
		$insert_data['filesize'] = $data['attach_filesize'];
		$insert_data['downloads'] = $data['attach_hits'];

		$insert_data['attachname'] = "post_".$insert_data['uid']."_".$data['attach_date'].".attach";
		$query = $db->simple_select("attachments", "aid", "attachname='".$db->escape_string($insert_data['attachname'])."'");
		if($db->num_rows($query) > 0)
		{
			$insert_data['attachname'] = "post_".$insert_data['uid']."_".$data['attach_date']."_".$data['attach_id'].".attach";
		}
	
		return $insert_data;
	}

	/**
	 * Get a attachment mime type from the IPB database
	 *
	 * @param string $ext Extension
	 * @return string The mime type
	 */
	function get_attach_type($ext)
	{
		$query = $this->old_db->simple_select("attachments_type", "atype_mimetype", "atype_extension = '{$ext}'");
		$results = $this->old_db->fetch_field($query, "atype_mimetype");
		$this->old_db->free_result($query);

		return $results;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of attachments
		if(!isset($import_session['total_attachments']))
		{
			$query = $this->old_db->simple_select("attachments", "COUNT(*) as count", "attach_rel_module='post'");
			$import_session['total_attachments'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_attachments'];
	}
}


