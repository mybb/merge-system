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

define('ATTACH_AS_DB', 0);
define('ATTACH_AS_FILES_OLD', 1);
define('ATTACH_AS_FILES_NEW', 2);

class VBULLETIN3_Converter_Module_Attachments extends Converter_Module_Attachments {

	var $settings = array(
		'friendly_name' => 'attachments',
		'progress_column' => 'attachmentid',
		'default_per_screen' => 20,
	);

	public $test_table = "attachment";

	public $path_column = "attachmentid,userid";

	var $attach_storage;

	function pre_setup()
	{
		global $mybb, $import_session;

		$query = $this->old_db->simple_select('setting', 'value', "varname='attachfile'");
		$this->attach_storage = $this->old_db->fetch_field($query, 'value');
		$this->old_db->free_result($query);

		// File is saved in the database, no need for an uploadspath!
		if($this->attach_storage == ATTACH_AS_DB)
		{
			$import_session['uploadspath'] = '';
			unset($mybb->input['uploadspath']);
		}

		parent::pre_setup();
	}

	function get_upload_path()
	{
		$query = $this->old_db->simple_select('setting', 'value', "varname='attachpath'");
		$uploadspath = $this->old_db->fetch_field($query, 'value');
		$this->old_db->free_result($query);
		return $uploadspath;
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

		$attach_details = $this->get_import->post_attachment_details($data['postid']);
		$insert_data['pid'] = $attach_details['pid'];
		$insert_data['posthash'] = md5($attach_details['tid'].$attach_details['uid'].random_str());

		$insert_data['uid'] = $this->get_import->uid($data['userid']);
		$insert_data['filename'] = $data['filename'];
		$insert_data['attachname'] = "post_".$insert_data['uid']."_".$data['dateline']."_".$data['attachmentid'].".attach";
		$insert_data['filesize'] = $data['filesize'];
		$insert_data['downloads'] = $data['counter'];
		$insert_data['visible'] = $data['visible'];

		if($data['thumbnail'])
		{
			$insert_data['thumbnail'] = str_replace(".attach", "_thumb.{$data['extension']}", $insert_data['attachname']);
		}

		return $insert_data;
	}

	/**
	 * Get the raw file data. vBulletin saves the full data in the database by default!
	 *
	 * @param array $unconverted_data
	 *
	 * @return string
	 */
	function get_file_data($unconverted_data)
	{
		if($this->attach_storage == ATTACH_AS_DB)
		{
			return $unconverted_data['filedata'];
		}
		return parent::get_file_data($unconverted_data);
	}

	/**
	 * Get a attachment mime type from the vB database
	 *
	 * @param string $ext Extension
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

	/**
	 * Original function from vB 3, modified for our needs
	 *
	 * @param array $attachment
	 * @return string
	 */
	function generate_raw_filename($attachment)
	{
		if ($this->attach_storage == ATTACH_AS_FILES_NEW) // expanded paths
		{
			$path = implode('/', preg_split('//', $attachment['userid'],  -1, PREG_SPLIT_NO_EMPTY));
		}
		else
		{
			$path = $attachment['userid'];
		}

		$path .= '/' . $attachment['attachmentid'] . '.attach';

		return $path;
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

		if($this->attach_storage != ATTACH_AS_DB)
		{
			echo '
<tr>
<th colspan="2" class="first last">' . $lang->sprintf($lang->module_attachment_link, $this->board->plain_bbname) . ':</th>
</tr>
<tr>
<td><label for="uploadspath"> ' . $lang->module_attachment_label . ':</label></td>
<td width="50%"><input type="text" name="uploadspath" id="uploadspath" value="' . $import_session['uploadspath'] . '" style="width: 95%;" /></td>
</tr>';
		}
	}
}


