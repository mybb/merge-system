<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2011 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class VBULLETIN3_Converter_Module_Attachments extends Converter_Module_Attachments {

	var $settings = array(
		'friendly_name' => 'attachments',
		'progress_column' => 'attachmentid',
		'default_per_screen' => 20,
	);

	function pre_setup()
	{
		$this->check_attachments_dir_perms();
	}

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select("attachment", "*", "", array('limit_start' => $this->trackers['start_attachments'], 'limit' => $import_session['attachments_per_screen']));
		while($attachment = $this->old_db->fetch_array($query))
		{
			$this->insert($attachment);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// vBulletin 3 values
		$insert_data['import_aid'] = $data['attachmentid'];
		$insert_data['filetype'] = $this->get_attach_type($data['extension']);

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

		$posthash = $this->get_import->post_attachment_details($data['postid']);
		$insert_data['pid'] = $posthash['pid'];
		if($posthash['posthash'])
		{
			$insert_data['posthash'] = $posthash['posthash'];
		}
		else
		{
			$insert_data['posthash'] = md5($posthash['tid'].$posthash['uid'].random_str());
		}

		$insert_data['uid'] = $this->get_import->uid($data['userid']);
		$insert_data['filename'] = $data['filename'];
		$insert_data['attachname'] = "post_".$insert_data['uid']."_".$data['dateline'].".attach";
		$insert_data['filesize'] = $data['filesize'];
		$insert_data['downloads'] = $data['counter'];
		$insert_data['visible'] = $data['visible'];

		if($data['thumbnail'])
		{
			$insert_data['thumbnail'] = str_replace(".attach", "_thumb.{$data['extension']}", $insert_data['attachname']);
		}

		return $insert_data;
	}

	function after_insert($data, $insert_data, $aid)
	{
		global $mybb, $db;

		if($data['thumbnail'])
		{
			// Transfer attachment thumbnails
			$file = @fopen($mybb->settings['uploadspath'].'/'.$insert_data['thumbnail'], 'w');
			if($file)
			{
				@fwrite($file, $data['thumbnail']);
			}
			else
			{
				$this->board->set_error_notice_in_progress("Error transfering the attachment thumbnail (ID: {$aid})");
			}
			@fclose($file);
			@my_chmod($mybb->settings['uploadspath'].'/'.$insert_data['thumbnail'], '0777');
		}

		// Transfer attachments
		$file = @fopen($mybb->settings['uploadspath'].'/'.$insert_data['attachname'], 'w');
		if($file)
		{
			@fwrite($file, $data['filedata']);
		}
		else
		{
			$this->board->set_error_notice_in_progress("Error transfering the attachment (ID: {$aid})");
		}
		@fclose($file);
		@my_chmod($mybb->settings['uploadspath'].'/'.$insert_data['attachname'], '0777');
	}

	/**
	 * Get a attachment mime type from the vB database
	 *
	 * @param string Extension
	 * @return string The mime type
	 */
	function get_attach_type($ext)
	{
		$query = $this->old_db->simple_select("attachmenttype", "mimetype", "extension = '{$ext}'");
		$mimetype = unserialize($this->old_db->fetch_field($query, "mimetype"));

		$results = str_replace('Content-type: ', '', $mimetype[0]);
		$this->old_db->free_result($query);

		return $results;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of attachments
		if(!isset($import_session['total_attachments']))
		{
			$query = $this->old_db->simple_select("attachment", "COUNT(*) as count");
			$import_session['total_attachments'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_attachments'];
	}
}

?>