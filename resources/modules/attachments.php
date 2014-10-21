<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 */

class Converter_Module_Attachments extends Converter_Module
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
	 * Insert attachment into database
	 *
	 * @param attachment The insert array going into the MyBB database
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

		if(!defined("IN_TESTING"))
		{
			$this->after_insert($unconverted_values, $converted_values, $aid);
		}

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

	public function test_readability($table, $path_column)
	{
		global $mybb, $import_session, $output, $lang;

		if($import_session['total_attachments'] <= 0)
		{
			return;
		}

		$this->debug->log->trace0("Checking readability of attachments from specified path");

		if($mybb->input['uploadspath'])
		{
			$import_session['uploadspath'] = $mybb->input['uploadspath'];
			if(substr($import_session['uploadspath'], strlen($import_session['uploadspath'])-1, 1) != '/')
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
		$query = $this->old_db->simple_select($table, $path_column);
		while($attachment = $this->old_db->fetch_array($query))
		{
			++$total;

			if(method_exists($this, "generate_raw_filename"))
			{
				$filename = $this->generate_raw_filename($attachment);
			}
			else
			{
				$filename = $attachment[$path_column];
			}

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
}

?>