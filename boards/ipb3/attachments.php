<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 *
 * $Id: attachments.php 4394 2010-12-14 14:38:21Z ralgith $
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

	function pre_setup()
	{
		global $mybb, $output, $import_session;

		// Set uploads path
		if(!isset($import_session['uploadspath']))
		{
			$query = $this->old_db->simple_select("conf_settings", "conf_value", "conf_key = 'upload_url'", array('limit' => 1));
			$import_session['uploadspath'] = $this->old_db->fetch_field($query, 'conf_value');
			$this->old_db->free_result($query);
		}

		$this->check_attachments_dir_perms();

		// Get number of polls per screen from form
		if(isset($mybb->input['attachments_per_screen']))
		{
			$import_session['attachments_per_screen'] = intval($mybb->input['attachments_per_screen']);
		}

		if($mybb->input['uploadspath'])
		{
			// Test our ability to read attachment files from the forum software
			$this->test_readability("attachments", "attach_location");
		}

		$output->print_inline_errors();
	}

	function import()
	{
		global $mybb, $output, $import_session;

		$query = $this->old_db->simple_select("attachments", "*", "", array('limit_start' => $this->trackers['start_attachments'], 'limit' => $import_session['attachments_per_screen']));
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

		// Invision Power Board 2 values
		$insert_data['import_aid'] = $data['attach_id'];

		$posthash = $this->get_import->post_attachment_details($data['attach_pid']);
		$insert_data['pid'] = $posthash['pid'];
		if($posthash['posthash'])
		{
			$insert_data['posthash'] = $posthash['posthash'];
		}
		else
		{
			$insert_data['posthash'] = md5($posthash['tid'].$posthash['uid'].random_str());
		}

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

		$insert_data['posthash'] = $data['attach_post_key'];
		$insert_data['uid'] = $this->get_import->uid($data['attach_member_id']);
		$insert_data['filename'] = $data['attach_file'];
		$insert_data['attachname'] = "post_".$insert_data['uid']."_".$data['attach_date'].".attach";
		$insert_data['filesize'] = $data['attach_filesize'];
		$insert_data['downloads'] = $data['attach_hits'];
		$insert_data['visible'] = $data['attach_approved'];

		if($data['attach_thumb_location'])
		{
			$ext = get_extension($data['attach_thumb_location']);
			$insert_data['thumbnail'] = str_replace(".attach", "_thumb.$ext", basename($insert_data['attachname']));
		}

		return $insert_data;
	}

	function after_insert($data, $insert_data, $aid)
	{
		global $mybb, $import_session;

		$thumb_not_exists = "";
		if($data['attach_thumb_location'])
		{
			// Transfer attachment thumbnail
			$data_thumbnail_file = merge_fetch_remote_file($import_session['uploadspath'].'/'.$data['attach_thumb_location']);

			if(!empty($data_thumbnail_file))
			{
				$attachrs = @fopen($mybb->settings['uploadspath'].'/'.$insert_data['thumbnail'], 'w');
				if($attachrs)
				{
					@fwrite($attachrs, $data_thumbnail_file);
				}
				else
				{
					$this->board->set_error_notice_in_progress("Error transfering the attachment thumbnail (ID: {$aid})");
				}
				@fclose($attachrs);

				@my_chmod($mybb->settings['uploadspath'].'/'.$insert_data['thumbnail'], '0777');
			}
			else
			{
				$this->board->set_error_notice_in_progress("Error could not find the attachment thumbnail (ID: {$aid})");
			}
		}

		// Transfer attachment
		$data_file = merge_fetch_remote_file($import_session['uploadspath'].'/'.$data['attach_location']);
		if(!empty($data_file))
		{
			$attachrs = @fopen($mybb->settings['uploadspath'].'/'.$insert_data['attachname'], 'w');
			if($attachrs)
			{
				@fwrite($attachrs, $data_file);
			}
			else
			{
				$this->board->set_error_notice_in_progress("Error transfering the attachment (ID: {$aid})");
			}
			@fclose($attachrs);

			@my_chmod($mybb->settings['uploadspath'].'/'.$insert_data['attachname'], '0777');
			$attach_not_exists = "";
		}
		else
		{
			$this->board->set_error_notice_in_progress("Error could not find the attachment (ID: {$aid})");
		}
	}

	function print_attachments_per_screen_page()
	{
		global $import_session;

		echo '<tr>
<th colspan="2" class="first last">Please type in the link to your '.$this->plain_bbname.' forum attachment directory:</th>
</tr>
<tr>
<td><label for="uploadspath"> Link (URL) to your forum attachment directory:
</label></td>
<td width="50%"><input type="text" name="uploadspath" id="uploadspath" value="'.$import_session['uploadspath'].'" style="width: 95%;" /></td>
</tr>';
	}

	/**
	 * Get a attachment mime type from the IPB database
	 *
	 * @param string Extension
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
			$query = $this->old_db->simple_select("attachments", "COUNT(*) as count");
			$import_session['total_attachments'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_attachments'];
	}
}

?>