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

class IPB4_Converter_Module_Attachments extends Converter_Module_Attachments {

	var $settings = array(
		'friendly_name' => "attachments",
		'progress_column' => "attach_id",
		'default_per_screen' => 20,
	);

	function pre_setup()
	{
		global $mybb, $output, $import_session;

		$this->check_attachments_dir_perms();

		// IPB 4 seems to save the full location
		$import_session['uploadspath'] = $mybb->input['uploadspath'] = "";

		// Test our ability to read attachment files from the forum software
		$this->test_readability("core_attachments", "attach_location");
	}

	function import()
	{
		global $mybb, $output, $import_session;

		$query = $this->old_db->query("SELECT *
				FROM ".OLD_TABLE_PREFIX."core_attachments a
				LEFT JOIN ".OLD_TABLE_PREFIX."core_attachments_map m ON(m.attachment_id=a.attach_id)
				WHERE m.location_key='forums_Forums'
				LIMIT {$this->trackers['start_attachments']}, {$import_session['attachments_per_screen']}");
		while($attachment = $this->old_db->fetch_array($query))
		{
			$this->insert($attachment);
		}
	}

	function convert_data($data)
	{
		global $db, $error_notice, $mybb;

		$error_notice = "";

		$insert_data = array();

		// Invision Power Board 4 values
		$insert_data['import_aid'] = $data['attach_id'];

		$attach_details = $this->get_import->post_attachment_details($data['id2']);
		$insert_data['pid'] = $attach_details['pid'];
		$insert_data['posthash'] = md5($attach_details['tid'].$attach_details['uid'].random_str());

		if(function_exists("finfo_open"))
		{
			$file_info = finfo_open(FILEINFO_MIME);
			list($insert_data['filetype'], ) = explode(';', finfo_file($file_info, $data['attach_location']), 1);
			finfo_close($file_info);
		}

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

	function after_insert($data, $insert_data, $aid)
	{
		global $mybb, $import_session, $lang;

		// Transfer attachment - IPB 4 saves the full path
		$data_file = merge_fetch_remote_file($data['attach_location']);
		if(!empty($data_file))
		{
			$attachrs = @fopen($mybb->settings['uploadspath'].'/'.$insert_data['attachname'], 'w');
			if($attachrs)
			{
				@fwrite($attachrs, $data_file);
			}
			else
			{
				$this->board->set_error_notice_in_progress($lang->sprintf($lang->module_attachment_error, $aid));
			}
			@fclose($attachrs);

			@my_chmod($mybb->settings['uploadspath'].'/'.$insert_data['attachname'], '0777');
			$attach_not_exists = "";
		}
		else
		{
			$this->board->set_error_notice_in_progress($lang->sprintf($lang->module_attachment_not_found, $aid));
		}
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of attachments
		if(!isset($import_session['total_attachments']))
		{
			$query = $this->old_db->query("SELECT COUNT(*) AS count
					FROM ".OLD_TABLE_PREFIX."core_attachments a
					LEFT JOIN ".OLD_TABLE_PREFIX."core_attachments_map m ON(m.attachment_id=a.attach_id)
					WHERE m.location_key='forums_Forums'");
			$import_session['total_attachments'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_attachments'];
	}
}

?>
