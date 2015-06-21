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

class SMF2_Converter_Module_Attachments extends Converter_Module_Attachments {

	var $settings = array(
		'friendly_name' => 'attachments',
		'progress_column' => 'id_attach',
		'default_per_screen' => 20,
	);

	var $thumbs = array();

	var $cache_attach_filenames = array();

	public $path_column = "id_attach,file_hash";

	function get_upload_path()
	{
		$query = $this->old_db->simple_select("settings", "value", "variable = 'attachmentUploadDir'", array('limit' => 1));
		$uploadspath = $this->old_db->fetch_field($query, 'value');
		$this->old_db->free_result($query);

		if(empty($uploadspath))
		{
			$query = $this->old_db->simple_select("settings", "value", "variable = 'avatar_url'", array('limit' => 1));
			$uploadspath = str_replace('avatars', 'attachments', $this->old_db->fetch_field($query, 'value'));
			$this->old_db->free_result($query);
		}

		return $uploadspath;
	}

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select("attachments", "*", "attachment_type != '3' AND id_msg != '0'", array('limit_start' => $this->trackers['start_attachments'], 'limit' => $import_session['attachments_per_screen']));
		while($attachment = $this->old_db->fetch_array($query))
		{
			if(in_array($attachment['id_attach'], $this->thumbs))
			{
				continue;
			}

			$this->insert($attachment);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// SMF values
		$insert_data['import_aid'] = $data['id_attach'];

		$insert_data['uid'] = $this->get_import->uid($data['id_member']);
		$insert_data['filename'] = $data['filename'];
		$insert_data['attachname'] = "post_".$insert_data['uid']."_".TIME_NOW."_".$data['id_attach'].".attach";
		$insert_data['filetype'] = $data['mime_type'];

		// Check if this is an image
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

		// Check if this is an image
		if($is_image == 1)
		{
			$insert_data['thumbnail'] = 'SMALL';
		}
		else
		{
			$insert_data['thumbnail'] = '';
		}

		$insert_data['filesize'] = $data['size'];
		$insert_data['downloads'] = $data['downloads'];

		$attach_details = $this->get_import->post_attachment_details($data['id_msg']);
		$insert_data['pid'] = $attach_details['pid'];
		$insert_data['posthash'] = md5($attach_details['tid'].$attach_details['uid'].random_str());

		if($data['id_thumb'] != 0)
		{
			$this->thumbs[] = $data['id_thumb'];

			$query = $this->old_db->simple_select("attachments", "*", "id_attach = '{$data['id_thumb']}'");
			$fdk = $this->old_db->fetch_array($query);
			$this->old_db->free_result($query);

			switch(strtolower($fdk['mime_type']))
			{
				case "image/gif":
					$ext = "gif";
					break;
				case "image/jpeg":
				case "image/x-jpg":
				case "image/x-jpeg":
				case "image/pjpeg":
				case "image/jpg":
					$ext = "jpg";
					break;
				case "image/png":
				case "image/x-png":
					$ext = "png";
					break;
				default:
					$ext = "";
					break;
			}

			$insert_data['thumbnail'] = str_replace(".attach", "_thumb.$ext", $insert_data['attachname']);
		}

		return $insert_data;
	}

	function get_import_attach_filename($aid)
	{
		if(array_key_exists($aid, $this->cache_attach_filenames))
		{
			return $this->cache_attach_filenames[$aid];
		}

		$query = $this->old_db->simple_select("attachments", "filename", "id_attach = '{$aid}'");
		$thumbnail = $this->old_db->fetch_array($query, "filename");
		$this->old_db->free_result($query);

		$this->cache_attach_filenames[$aid] = $thumbnail;

		return $thumbnail;
	}

	function generate_raw_filename($attachment)
	{
		return $attachment['id_attach']."_".$attachment['file_hash'];
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of attachments
		if(!isset($import_session['total_attachments']))
		{
			$query = $this->old_db->simple_select("attachments", "COUNT(*) as count", "attachment_type != '3' AND id_msg != '0'");
			$import_session['total_attachments'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_attachments'];
	}
}


