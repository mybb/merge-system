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

class XENFORO_Converter_Module_Attachments extends Converter_Module_Attachments {

	var $settings = array(
		'friendly_name' => 'attachments',
		'progress_column' => 'attachment_id',
		'default_per_screen' => 20,
	);
	
	function pre_setup()
	{
		global $import_session, $mybb;

		// Set uploads path
		if(!isset($import_session['uploadspath']))
		{
			$query = $this->old_db->simple_select("option", "option_value", "option_id='boardUrl'");
			$import_session['uploadspath'] = $this->old_db->fetch_field($query, "option_value");
			$import_session['uploadspath'] .= "/internal_data/attachments";
		}

		$this->check_attachments_dir_perms();
		if($mybb->input['uploadspath'])
		{
			// Test our ability to read attachment files from the forum software
			$this->test_readability("attachment", "attachment_id, data_id");
		}
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
		
		// Xenforo 1 values
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
	
	function after_insert($data, $insert_data, $aid)
	{
		global $mybb, $db, $import_session, $lang;

		// Transfer attachment
		$attachment_file = merge_fetch_remote_file($import_session['uploadspath'].'/'.$this->generate_raw_filename($data));
		if(!empty($attachment_file))
		{
			$attachrs = @fopen($mybb->settings['uploadspath'].'/'.$insert_data['attachname'], 'w');
			if($attachrs)
			{
				@fwrite($attachrs, $attachment_file);
			}
			else
			{
				$this->board->set_error_notice_in_progress($lang->sprintf($lang->module_attachment_error, $aid));
			}
			@fclose($attachrs);
			@my_chmod($mybb->settings['uploadspath'].'/'.$insert_data['attachname'], '0777');
		}
		else
		{
			$this->board->set_error_notice_in_progress($lang->sprintf($lang->module_attachment_not_found, $aid));
		}

		$attach_details = $this->get_import->post_attachment_details($data['content_id']);
		$db->write_query("UPDATE ".TABLE_PREFIX."threads SET attachmentcount = attachmentcount + 1 WHERE tid = '".$attach_details['tid']."'");
	}

	function generate_raw_filename($attach)
	{
		if(!isset($attach['file_hash']))
		{
			$query = $this->old_db->simple_select("attachment_data", "file_hash,filename", "data_id='{$attach['data_id']}'");
			$data = $this->old_db->fetch_array($query);
			$this->old_db->free_result($query);
			$attach = array_merge($attach, $data);
		}
		$name = floor($attach['data_id']/1000)."/{$attach['data_id']}-{$attach['file_hash']}.data";

		return $name;
	}

	function print_attachments_per_screen_page()
	{
		global $import_session, $lang;

		echo '<tr>
<th colspan="2" class="first last">'.$lang->sprintf($lang->module_attachment_link, $this->board->plain_bbname).':</th>
</tr>
<tr>
<td><label for="uploadspath"> '.$lang->module_attachment_label.':</label></td>
<td width="50%"><input type="text" name="uploadspath" id="uploadspath" value="'.$import_session['uploadspath'].'" style="width: 95%;" /></td>
</tr>';
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

?>
