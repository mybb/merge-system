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

class PHPBB3_Converter_Module_Attachments extends Converter_Module_Attachments {

	var $settings = array(
		'friendly_name' => 'attachments',
		'progress_column' => 'attach_id',
		'default_per_screen' => 20,
	);

	function pre_setup()
	{
		global $import_session, $output, $mybb;

		// Set uploads path
		if(!isset($import_session['uploadspath']))
		{
			$query = $this->old_db->simple_select("config", "config_value", "config_name = 'server_protocol'", array('limit' => 1));
			$import_session['uploadspath'] = $this->old_db->fetch_field($query, 'config_value');
			$this->old_db->free_result($query);

			$query = $this->old_db->simple_select("config", "config_value", "config_name = 'server_name'", array('limit' => 1));
			$import_session['uploadspath'] .= $this->old_db->fetch_field($query, 'config_value');
			$this->old_db->free_result($query);

			$query = $this->old_db->simple_select("config", "config_value", "config_name = 'script_path'", array('limit' => 1));
			$import_session['uploadspath'] .= $this->old_db->fetch_field($query, 'config_value').'/';
			$this->old_db->free_result($query);

			$query = $this->old_db->simple_select("config", "config_value", "config_name = 'upload_path'", array('limit' => 1));
			$import_session['uploadspath'] .= $this->old_db->fetch_field($query, 'config_value');
			$this->old_db->free_result($query);
		}

		$this->check_attachments_dir_perms();

		if($mybb->input['uploadspath'])
		{
			// Test our ability to read attachment files from the forum software
			$this->test_readability("attachments", "physical_filename");
		}
	}

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select("attachments", "*", "", array('limit_start' => $this->trackers['start_attachments'], 'limit' => $import_session['attachments_per_screen']));
		while($attachment = $this->old_db->fetch_array($query))
		{
			$this->insert($attachment);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// phpBB 3 values
		$insert_data['import_aid'] = $data['attach_id'];
		$insert_data['uid'] = $this->get_import->uid($data['poster_id']);
		$insert_data['filename'] = $data['real_filename'];
		$insert_data['attachname'] = "post_".$insert_data['uid']."_".$data['filetime'].".attach";;
		$insert_data['filetype'] = $data['mimetype'];
		$insert_data['filesize'] = $data['filesize'];
		$insert_data['downloads'] = $data['download_count'];

		$attach_details = $this->get_import->post_attachment_details($data['post_msg_id']);

		$insert_data['pid'] = $attach_details['pid'];
		$insert_data['posthash'] = md5($attach_details['tid'].$attach_details['uid'].random_str());

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

		return $insert_data;
	}

	function after_insert($data, $insert_data, $aid)
	{
		global $mybb, $db, $import_session, $lang;

		// Transfer attachment
		$attachment_file = merge_fetch_remote_file($import_session['uploadspath'].'/'.$data['physical_filename']);
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

		$attach_details = $this->get_import->post_attachment_details($data['post_msg_id']);
		$db->write_query("UPDATE ".TABLE_PREFIX."threads SET attachmentcount = attachmentcount + 1 WHERE tid = '".$attach_details['tid']."'");
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

	function test()
	{
		// poster_id -> array
		$this->get_import->cache_post_attachment_details = array(
			5 => array(
				'posthash' => 'dsrw4eqwsd34255676ffsd#@!',
				'tid' => 2,
				'uid' => 3,
				'pid' => 4)
		);

		// import_uid -> uid
		$this->get_import->cache_uids = array(
			5 => 11,
		);

		$data = array(
			'attach_id' => 1,
			'poster_id' => 5,
			'real_filename' => 'testests.png',
			'filetime' => 12345678,
			'mimetype' => 'image/png',
			'filesize' => 1234,
			'download_count' => 500,
		);

		$match_data = array(
			'import_aid' => 1,
			'uid' => 11,
			'filename' => 'testests.png',
			'attachname' => 'post_11_12345678.attach',
			'filetype' => 'image/png',
			'filesize' => 1234,
			'downloads' => 500,
			'pid' => 4,
			'posthash' => 'dsrw4eqwsd34255676ffsd#@!',
			'thumbnail' => 'SMALL',
		);

		$this->assert($data, $match_data);
	}

	/**
	 * Checks if a URL exists (if it is correct or not)
	 *
	 * @param string url to check
	 * @return boolean true if the url is correct, false otherwise
	 */
	function url_exists($url)
	{
		$file = merge_fetch_remote_file($url);
 		if(!$file)
		{
  			return false;
		}
 		return true;
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