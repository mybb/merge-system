<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 */

define('AVATAR_TYPE_NONE', '');
define('AVATAR_TYPE_UPLOAD', 'upload');
define('AVATAR_TYPE_URL', 'remote');
define('AVATAR_TYPE_GRAVATAR', 'gravatar');

abstract class Converter_Module_Avatars extends Converter_Module
{
	/**
	 * @var string
	 */
	public $path_column = "";

	/**
	 * @var string
	 */
	public $test_table = "";

	abstract function get_avatar_path();

	function pre_setup()
	{
		global $mybb, $import_session;

		// Always check whether we can write to our own directory first
		$this->check_avatar_dir_perms();

		// Do we still need to set the uploads path?
		if(!isset($import_session['avatarspath']))
		{
			$import_session['avatarspath'] = $this->get_avatar_path();

			// Make sure it ends on a slash if it's not empty - helps later
			if(!empty($import_session['avatarspath']) && my_substr($import_session['avatarspath'], -1) != '/') {
				$import_session['avatarspath'] .= '/';
			}
		}

		// Test whether we can read
		if(isset($mybb->input['avatarspath']))
		{
			$this->test_readability();
		}
	}

	/**
	 * Insert avatar into database
	 *
	 * @param array $data The insert array going into the MyBB database
	 * @return int The new id
	 */
	public function insert($data)
	{
		global $db, $output;

		$this->debug->log->datatrace('$data', $data);

		$output->print_progress("start", $data[$this->settings['progress_column']]);

		$unconverted_values = $data;

		// Call our currently module's process function
		$data = $converted_values = $this->convert_data($data);

		// Should loop through and fill in any values that aren't set based on the MyBB db schema or other standard default values and escape them properly
		$insert_array = $this->prepare_insert_array($data);

		unset($insert_array['avatar_type']);

		if(empty($insert_array['uid'])) {
			echo "Internal Error, uid not set<br /><pre>";
			var_dump($insert_array);
			echo "</pre>";
			die();
		}

		$this->debug->log->datatrace('$insert_array', $insert_array);

		$db->update_query("users", $insert_array, "uid='{$insert_array['uid']}'");

		$this->after_insert($unconverted_values, $converted_values, $insert_array['uid']);

		$this->increment_tracker('avatars');

		$output->print_progress("end");

		return $insert_array['uid'];
	}

	public function check_avatar_dir_perms()
	{
		global $import_session, $output, $lang;

		if($import_session['total_avatars'] <= 0)
		{
			return;
		}

		$this->debug->log->trace0("Checking avatar directory permissions again");

		if($import_session['uploads_avatars_test'] != 1)
		{
			// Check upload directory is writable
			$uploadswritable = @fopen(MYBB_ROOT.'uploads/avatars/test.write', 'w');
			if(!$uploadswritable)
			{
				$this->debug->log->error("Avatars directory is not writable");
				// TODO: Does that language string match?
				$this->errors[] = $lang->attmodule_notwritable.'<a href="http://docs.mybb.com/CHMOD_Files.html" target="_blank">'.$lang->attmodule_chmod.'</a>';
				@fclose($uploadswritable);
				$output->print_error_page();
			}
			else
			{
				@fclose($uploadswritable);
			  	@my_chmod(MYBB_ROOT.'uploads/avatars', '0777');
			  	@my_chmod(MYBB_ROOT.'uploads/avatars/test.write', '0777');
				@unlink(MYBB_ROOT.'uploads/avatars/test.write');
				$import_session['uploads_avatars_test'] = 1;
				$this->debug->log->trace1("Avatars directory is writable");
			}
		}
	}

	public function test_readability()
	{
		global $mybb, $import_session, $lang;

		if($import_session['total_avatars'] <= 0)
		{
			return;
		}

		$this->debug->log->trace0("Checking readability of avatars from specified path");

		if($mybb->input['avatarspath'])
		{
			$import_session['avatarspath'] = $mybb->input['avatarspath'];
			if(!empty($import_session['avatarspath']) && my_substr($import_session['avatarspath'], -1) != '/')
			{
				$import_session['avatarspath'] .= '/';
			}
		}

		if(strpos($mybb->input['avatarspath'], "localhost") !== false)
		{
			$this->errors[] = "<p>{$lang->attmodule_ipadress}</p>";
			$import_session['uploads_avatars_test'] = 0;
		}

		if(strpos($mybb->input['avatarspath'], "127.0.0.1") !== false)
		{
			$this->errors[] = "<p>{$lang->attmodule_ipadress2}</p>";
			$import_session['uploads_avatars_test'] = 0;
		}

		// TODO: Avatars are usually saved somewhere special. Also this should only check "uploaded" avatars
/*		$readable = $total = 0;
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
		$this->old_db->free_result($query);*/

		// If less than 5% of our avatars are readable then it seems like we don't have a good uploads path set.
		if((($readable/$total)*100) < 5)
		{
			$this->debug->log->error("Not enough avatar could be read: ".(($readable/$total)*100)."%");
			// TODO: Does this lang string match?
			$this->errors[] = $lang->attmodule_notread.'<a href="http://docs.mybb.com/CHMOD_Files.html" target="_blank">'.$lang->attmodule_chmod.'</a>'.$lang->attmodule_notread2;
			$this->is_errors = true;
			$import_session['uploads_avatars_test'] = 0;
		}
	}

	function after_insert($unconverted_data, $converted_data, $aid)
	{
		global $mybb, $import_session, $lang;

		if($converted_data['avatartype'] != AVATAR_TYPE_UPLOAD) {
			return;
		}

		// Transfer avatar
		$data_file = merge_fetch_remote_file($import_session['avatarspath'].$this->generate_raw_filename($unconverted_data));
		if(!empty($data_file))
		{
			$avatar = @fopen(str_replace('./', $mybb->settings['bburl'].'/', $converted_data['avatar']), 'w');
			if($avatar)
			{
				@fwrite($avatar, $data_file);
			}
			else
			{
				// TODO: langstring
				$this->board->set_error_notice_in_progress($lang->sprintf($lang->module_attachment_error, $aid));
			}
			@fclose($avatar);

			@my_chmod(str_replace('./', $mybb->settings['bburl'].'/', $converted_data['avatar']), '0777');
		}
		else
		{
			// TODO: Langstring
			$this->board->set_error_notice_in_progress($lang->sprintf($lang->module_attachment_not_found, $aid));
		}
	}

	function print_avatars_per_screen_page()
	{
		global $import_session, $lang;

		// TODO: langstring
		echo '<tr>
<th colspan="2" class="first last">'.$lang->sprintf($lang->module_attachment_link, $this->board->plain_bbname).':</th>
</tr>
<tr>
<td><label for="avatarspath"> '.$lang->module_attachment_label.':</label></td>
<td width="50%"><input type="text" name="avatarspath" id="avatarspath" value="'.$import_session['avatarspath'].'" style="width: 95%;" /></td>
</tr>';
	}

	/**
	 * @param array $avatar
	 *
	 * @return bool|string
	 */
	function generate_raw_filename($avatar)
	{
		return isset($avatar[$this->path_column]) ? $avatar[$this->path_column]: '';
	}
}


