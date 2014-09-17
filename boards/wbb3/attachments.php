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

class WBB3_Converter_Module_Attachments extends Converter_Module_Attachments {

	var $settings = array(
		'friendly_name' => 'attachments',
		'progress_column' => 'attachmentID',
		'default_per_screen' => 20,
	);

	function pre_setup()
	{
		global $import_session, $output, $mybb;
		// Set uploads path
		if(!isset($import_session['uploadspath']))
		{
			$query = $this->old_db->simple_select(WCF_PREFIX."option", "optionValue", "optionName='page_url' AND optionValue!=''");
			$import_session['uploadspath'] = $this->old_db->fetch_field($query, "optionValue");
			$import_session['uploadspath'] .= "/wcf/attachments";
		}

		$this->check_attachments_dir_perms();
		if($mybb->input['uploadspath'])
		{
			// Test our ability to read attachment files from the forum software
			$this->test_readability(WCF_PREFIX."attachment", "attachmentID");
		}

	}

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select(WCF_PREFIX."attachment", "*", "containerType='post'", array('limit_start' => $this->trackers['start_attachments'], 'limit' => $import_session['attachments_per_screen']));
		while($attachment = $this->old_db->fetch_array($query))
		{
			$this->insert($attachment);
		}
	}

	function convert_data($data)
	{
		global $db;

		$insert_data = array();

		// WBB 3 values
		$insert_data['import_aid'] = $data['attachmentID'];
		$insert_data['uid'] = $this->get_import->uid($data['userID']);
		$insert_data['filename'] = $data['attachmentName'];
		$insert_data['filetype'] = $data['fileType'];
		$insert_data['filesize'] = $data['attachmentSize'];
		$insert_data['downloads'] = $data['downloads'];

		$attach_details = $this->get_import->post_attachment_details($data['containerID']);

		$insert_data['pid'] = $attach_details['pid'];
		$insert_data['posthash'] = md5($attach_details['tid'].$attach_details['uid'].random_str());

		// Check if this is an image
		if($data['isImage'])
		{
			$insert_data['thumbnail'] = 'SMALL';
		}
		else
		{
			$insert_data['thumbnail'] = '';
		}

		// Build name and check whether it's already in use
		$insert_data['attachname'] = "post_".$insert_data['uid']."_".$data['uploadTime'].".attach";
		$query = $db->simple_select("attachments", "aid", "attachname='".$db->escape_string($insert_data['attachname'])."'");
		if($db->num_rows($query) > 0)
		{
			$insert_data['attachname'] = "post_".$insert_data['uid']."_".$data['uploadTime']."_".$data['attachmentID'].".attach";
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

		$attach_details = $this->get_import->post_attachment_details($data['containerID']);
		$db->write_query("UPDATE ".TABLE_PREFIX."threads SET attachmentcount = attachmentcount + 1 WHERE tid = '".$attach_details['tid']."'");
	}

	function generate_raw_filename($attach)
	{
		return "attachment-".$attach['attachmentID'];
	}

	function print_attachments_per_screen_page()
	{
		global $import_session, $lang;

		echo '<tr>
<th colspan="2" class="first last">'.$lang->sprintf($lang->module_attachment_link, $this->plain_bbname).':</th>
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
			$query = $this->old_db->simple_select(WCF_PREFIX."attachment", "COUNT(*) as count", "containerType='post'");
			$import_session['total_attachments'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_attachments'];
	}
}

?>