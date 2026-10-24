<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 *
 * Discourse Attachment Converter
 * Discourse stores attachments as uploads referenced by post_uploads.
 * Uploads have a SHA1-based path: /uploads/default/original/1X/{sha1}.{ext}
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class DISCOURSE_Converter_Module_Attachments extends Converter_Module_Attachments {

	var $settings = array(
		'friendly_name' => "attachments",
		'progress_column' => "id",
		'default_per_screen' => 20,
	);

	public $path_column = "url";

	public $test_table = "uploads";

	function get_upload_path()
	{
		global $mybb;
		$mybb->input['uploadspath'] = "";
		return "";
	}

	function import()
	{
		global $import_session;

		// Get uploads that are attached to posts (via post_uploads)
		$query = $this->old_db->query("
			SELECT u.*, pu.post_id
			FROM ".OLD_TABLE_PREFIX."uploads u
			LEFT JOIN ".OLD_TABLE_PREFIX."post_uploads pu ON (pu.upload_id = u.id)
			WHERE pu.post_id IS NOT NULL
			LIMIT ".$import_session['attachments_per_screen']." OFFSET ".$this->trackers['start_attachments']."
		");
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

		$insert_data['import_aid'] = $data['id'];

		// Post association
		$post_details = $this->get_import->post_attachment_details($data['post_id']);
		$insert_data['pid'] = $post_details['pid'];
		$insert_data['posthash'] = md5($post_details['tid'].$post_details['uid'].random_str());

		// File type detection
		if(function_exists("finfo_open"))
		{
			$file_info = finfo_open(FILEINFO_MIME);
			list($insert_data['filetype'], ) = explode(';', finfo_file($file_info, $this->generate_raw_filename($data)), 1);
			finfo_close($file_info);
		}

		// Check if it is an image
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

		if($is_image == 1)
		{
			$insert_data['thumbnail'] = 'SMALL';
		}
		else
		{
			$insert_data['thumbnail'] = '';
		}

		$insert_data['uid'] = $this->get_import->uid($data['user_id']);
		$insert_data['filename'] = $data['original_filename'];
		$insert_data['filesize'] = $data['filesize'];
		$insert_data['downloads'] = 0;

		$insert_data['attachname'] = "post_".$insert_data['uid']."_".strtotime($data['created_at']).".attach";
		$query = $db->simple_select("attachments", "aid", "attachname='".$db->escape_string($insert_data['attachname'])."'");
		if($db->num_rows($query) > 0)
		{
			$insert_data['attachname'] = "post_".$insert_data['uid']."_".strtotime($data['created_at'])."_".$data['id'].".attach";
		}

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		if(!isset($import_session['total_attachments']))
		{
			$query = $this->old_db->query("
				SELECT COUNT(*) as count
				FROM ".OLD_TABLE_PREFIX."uploads u
				LEFT JOIN ".OLD_TABLE_PREFIX."post_uploads pu ON (pu.upload_id = u.id)
				WHERE pu.post_id IS NOT NULL
			");
			$import_session['total_attachments'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_attachments'];
	}

	function generate_raw_filename($data)
	{
		return $data['url'];
	}

	function print_attachments_per_screen_page()
	{
		global $import_session, $lang;

		$yes_thumb_check = 'checked="checked"';
		$no_thumb_check = '';
		if(isset($import_session['attachments_create_thumbs']) && !$import_session['attachments_create_thumbs']) {
			$yes_thumb_check = '';
			$no_thumb_check = 'checked="checked"';
		}

		echo '<tr>
<th colspan="2" class="first last">'.$lang->module_attachment_create_thumbnail.'</th>
</tr>
<tr>
<td>'.$lang->module_attachment_create_thumbnail.'<br /><span class="smalltext">'.$lang->module_attachment_create_thumbnail_note.'</span></td>
<td width="50%"><input type="radio" name="attachments_create_thumbs" id="thumb_yes" value="1" '.$yes_thumb_check.'/> <label for="thumb_yes">'.$lang->yes.'</label>
<input type="radio" name="attachments_create_thumbs" id="thumb_no" value="0" '.$no_thumb_check.' /> <label for="thumb_no">'.$lang->no.'</label> </td>
</tr>';
	}
}
