<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 */

abstract class Converter_Module_Attachments extends Converter_Module
{
	public $default_values = array(
		'import_aid' => 0,
		'pid' => 0,
		'uid' => 0,
		'filename' => '',
		'filetype' => '',
		'filesize' => 0,
		'attachname' => '',
		'downloads' => 0,
		'dateuploaded' => 0,
		'visible' => 1,
		'thumbnail' => ''
	);

	public $integer_fields = array(
		'import_aid',
		'pid',
		'uid',
		'filesize',
		'downloads',
		'dateuploaded',
		'visible',
	);

	/**
	 * @var string
	 */
	public $path_column = "";

	/**
	 * @var string
	 */
	public $test_table = "attachments";

	/**
	 * @var array
	 */
	private $thread_cache = array();

	abstract function get_upload_path();

	function pre_setup()
	{
		global $mybb, $import_session;

		// Always check whether we can write to our own directory first
		$this->check_attachments_dir_perms();

		if(isset($mybb->input['attachments_create_thumbs']))
		{
			$import_session['attachments_create_thumbs'] = $mybb->input['attachments_create_thumbs'];
		}

		// Do we still need to set the uploads path?
		if(!isset($import_session['uploadspath']))
		{
			$import_session['uploadspath'] = $this->get_upload_path();

			// Make sure it ends on a slash if it's not empty - helps later
			if(!empty($import_session['uploadspath']) && my_substr($import_session['uploadspath'], -1) != '/') {
				$import_session['uploadspath'] .= '/';
			}
		}

		// Test whether we can read
		if(isset($mybb->input['uploadspath']))
		{
			$this->test_readability();
		}
	}

	/**
	 * Insert attachment into database
	 *
	 * @param array $data The insert array going into the MyBB database
	 * @return int The new id
	 */
	public function insert($data)
	{
		global $db, $output;

		// vB saves files in the database but we don't want them in the log
		$deb = $data;
		if(!empty($deb['filedata']) || !empty($deb['thumbnail']))
		{
			$deb['filedata'] = "[Skipped]";
			$deb['thumbnail'] = "[Skipped]";
		}
		$this->debug->log->datatrace('$data', $deb);

		$output->print_progress("start", $data[$this->settings['progress_column']]);

		$unconverted_values = $data;

		// Call our currently module's process function
		$data = $converted_values = $this->convert_data($data);

		// Should loop through and fill in any values that aren't set based on the MyBB db schema or other standard default values and escape them properly
		$insert_array = $this->prepare_insert_array($data);

		$this->debug->log->datatrace('$insert_array', $insert_array);

		$db->insert_query("attachments", $insert_array);
		$aid = $db->insert_id();

		// Let's change the bbcodes for this attachment
		$insert_array['aid'] = $aid;
		$this->bbcode_parser->change_attachment($insert_array);

		$this->after_insert($unconverted_values, $converted_values, $aid);

		$this->increment_tracker('attachments');

		$output->print_progress("end");

		return $aid;
	}

	public function check_attachments_dir_perms()
	{
		global $import_session, $output, $lang;

		if($import_session['total_attachments'] <= 0)
		{
			return;
		}

		$this->debug->log->trace0("Checking attachment directory permissions again");

		if($import_session['uploads_test'] != 1)
		{
			// Check upload directory is writable
			$uploadswritable = @fopen(MYBB_ROOT.'uploads/test.write', 'w');
			if(!$uploadswritable)
			{
				$this->debug->log->error("Uploads directory is not writable");
				$this->errors[] = $lang->attmodule_notwritable.'<a href="http://docs.mybb.com/CHMOD_Files.html" target="_blank">'.$lang->attmodule_chmod.'</a>';
				@fclose($uploadswritable);
				$output->print_error_page();
			}
			else
			{
				@fclose($uploadswritable);
			  	@my_chmod(MYBB_ROOT.'uploads', '0777');
			  	@my_chmod(MYBB_ROOT.'uploads/test.write', '0777');
				@unlink(MYBB_ROOT.'uploads/test.write');
				$import_session['uploads_test'] = 1;
				$this->debug->log->trace1("Uploads directory is writable");
			}
		}
	}

	public function test_readability()
	{
		global $mybb, $import_session, $lang;

		if($import_session['total_attachments'] <= 0)
		{
			return;
		}

		$this->debug->log->trace0("Checking readability of attachments from specified path");

		if($mybb->input['uploadspath'])
		{
			$import_session['uploadspath'] = $mybb->input['uploadspath'];
			if(!empty($import_session['uploadspath']) && my_substr($import_session['uploadspath'], -1) != '/')
			{
				$import_session['uploadspath'] .= '/';
			}
		}

		if(strpos($mybb->input['uploadspath'], "localhost") !== false)
		{
			$this->errors[] = "<p>{$lang->attmodule_ipadress}</p>";
			$import_session['uploads_test'] = 0;
		}

		if(strpos($mybb->input['uploadspath'], "127.0.0.1") !== false)
		{
			$this->errors[] = "<p>{$lang->attmodule_ipadress2}</p>";
			$import_session['uploads_test'] = 0;
		}

		$readable = $total = 0;
		$query = $this->old_db->simple_select($this->test_table, $this->path_column);
		while($attachment = $this->old_db->fetch_array($query))
		{
			++$total;

			$filename = $this->generate_raw_filename($attachment);

			// If this is a relative or absolute server path, use is_readable to check
			if(strpos($import_session['uploadspath'], '../') !== false || my_substr($import_session['uploadspath'], 0, 1) == '/' || my_substr($import_session['uploadspath'], 1, 1) == ':')
			{
				if(@is_readable($import_session['uploadspath'].$filename))
				{
					++$readable;
				}
			}
			else
			{
				if(check_url_exists($import_session['uploadspath'].$filename))
				{
					++$readable;
				}
			}
		}
		$this->old_db->free_result($query);

		// If less than 5% of our attachments are readable then it seems like we don't have a good uploads path set.
		if((($readable/$total)*100) < 5)
		{
			$this->debug->log->error("Not enough attachments could be read: ".(($readable/$total)*100)."%");
			$this->errors[] = $lang->attmodule_notread.'<a href="http://docs.mybb.com/CHMOD_Files.html" target="_blank">'.$lang->attmodule_chmod.'</a>'.$lang->attmodule_notread2;
			$this->is_errors = true;
			$import_session['uploads_test'] = 0;
		}
	}

	function after_insert($unconverted_data, $converted_data, $aid)
	{
		global $mybb, $import_session, $lang, $db;

		// Transfer attachment
		$data_file = merge_fetch_remote_file($import_session['uploadspath'].$this->generate_raw_filename($unconverted_data));
		if(!empty($data_file))
		{
			$attachrs = @fopen($mybb->settings['uploadspath'].'/'.$converted_data['attachname'], 'w');
			if($attachrs)
			{
				@fwrite($attachrs, $data_file);
			}
			else
			{
				$this->board->set_error_notice_in_progress($lang->sprintf($lang->module_attachment_error, $aid));
			}
			@fclose($attachrs);

			@my_chmod($mybb->settings['uploadspath'].'/'.$converted_data['attachname'], '0777');

			if($import_session['attachments_create_thumbs']) {
				require_once MYBB_ROOT."inc/functions_image.php";
				$ext = my_strtolower(my_substr(strrchr($converted_data['filename'], "."), 1));
				if($ext == "gif" || $ext == "png" || $ext == "jpg" || $ext == "jpeg" || $ext == "jpe")
				{
					$thumbname = str_replace(".attach", "_thumb.$ext", $converted_data['attachname']);
					$thumbnail = generate_thumbnail($mybb->settings['uploadspath'].'/'.$converted_data['attachname'], $mybb->settings['uploadspath'], $thumbname, $mybb->settings['attachthumbh'], $mybb->settings['attachthumbw']);
					if($thumbnail['code'] == 4)
					{
						$thumbnail['filename'] = "SMALL";
					}
					$db->update_query("attachments", array("thumbnail" => $thumbnail['filename']), "aid='{$aid}'");
				}
			}
		}
		else
		{
			$this->board->set_error_notice_in_progress($lang->sprintf($lang->module_attachment_not_found, $aid));
		}

		if(!isset($this->thread_cache[$converted_data['pid']])) {
			$query = $db->simple_select("posts", "tid", "pid={$converted_data['pid']}");
			$this->thread_cache[$converted_data['pid']] = $db->fetch_field($query, "tid");
		}
		// TODO: This may not work with SQLite/PgSQL
		$db->write_query("UPDATE ".TABLE_PREFIX."threads SET attachmentcount = attachmentcount + 1 WHERE tid = '".$this->thread_cache[$converted_data['pid']]."'");
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
</tr>
<tr>
<th colspan="2" class="first last">'.$lang->sprintf($lang->module_attachment_link, $this->board->plain_bbname).':</th>
</tr>
<tr>
<td><label for="uploadspath"> '.$lang->module_attachment_label.':</label></td>
<td width="50%"><input type="text" name="uploadspath" id="uploadspath" value="'.$import_session['uploadspath'].'" style="width: 95%;" /></td>
</tr>';
	}

	/**
	 * @param array $attachment
	 *
	 * @return bool|string
	 */
	function generate_raw_filename($attachment)
	{
		return isset($attachment[$this->path_column]) ? $attachment[$this->path_column]: '';
	}
}


