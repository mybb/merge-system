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
	public $default_values = array(
		'uid' => 0,
		'avatar' => '',
		'avatardimensions' => '',
		'avatartype' => '',
	);

	public $integer_fields = array(
		'uid',
	);

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

		// vB saves files in the database but we don't want them in the log
		$deb = $data;
		if(!empty($deb['filedata']) || !empty($deb['filedata_thumb']))
		{
			$deb['filedata'] = "[Skipped]";
			$deb['filedata_thumb'] = "[Skipped]";
		}
		$this->debug->log->datatrace('$data', $deb);

		$output->print_progress("start", $data[$this->settings['progress_column']]);

		$unconverted_values = $data;

		// Call our currently module's process function
		$data = $converted_values = $this->convert_data($data);

		// Should loop through and fill in any values that aren't set based on the MyBB db schema or other standard default values and escape them properly
		$insert_array = $this->prepare_insert_array($data, 'users');

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
				$this->errors[] = $lang->sprintf($lang->upload_not_writeable, 'uploads/avatar/');
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

		if($import_session['total_avatars'] <= 0 || SKIP_AVATAR_FILES)
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

		if(strpos($import_session['avatarspath'], '../') !== false || my_substr($import_session['avatarspath'], 0, 1) == '/' || my_substr($import_session['avatarspath'], 1, 1) == ':')
		{
			$readable = @is_readable($import_session['avatarspath']);
		}
		else
		{
			$readable = check_url_exists($import_session['avatarspath']);
		}

		if(!$readable) {
			$this->debug->log->error("Avatar directory not readable");
			$this->errors[] = $lang->download_not_readable;
			$import_session['uploads_avatars_test'] = 0;
		}

		if(!empty($this->errors)) {
			$this->is_errors = true;
		}
	}

	function after_insert($unconverted_data, $converted_data, $aid)
	{
		global $lang;

		if($converted_data['avatartype'] != AVATAR_TYPE_UPLOAD || SKIP_AVATAR_FILES) {
			return;
		}

		// Transfer avatar
		$file_data = $this->get_file_data($unconverted_data);
		if(!empty($file_data))
		{
			if(substr($converted_data['avatar'], 0, 2) == "./" || substr($converted_data['avatar'], 0, 3) == "../")
			{
				$converted_data['avatar'] = MYBB_ROOT.$converted_data['avatar'];
			}
			$converted_data['avatar'] = my_substr($converted_data['avatar'], 0, strpos($converted_data['avatar'], '?'));
			$avatar = @fopen($converted_data['avatar'], 'w');
			if($avatar)
			{
				@fwrite($avatar, $file_data);
			}
			else
			{
				$this->board->set_error_notice_in_progress($lang->sprintf($lang->module_avatar_error, $aid));
			}
			@fclose($avatar);

			@my_chmod($converted_data['avatar'], '0777');
		}
		else
		{
			$this->board->set_error_notice_in_progress($lang->sprintf($lang->module_avatar_not_found, $aid));
		}
	}

	/**
	 * Get the raw file data. Usually it tries to fetch a remote file using "generate_raw_filename"
	 *
	 * @param array $unconverted_data
	 *
	 * @return string
	 */
	function get_file_data($unconverted_data)
	{
		global $import_session;
		return merge_fetch_remote_file($import_session['avatarspath'].$this->generate_raw_filename($unconverted_data));
	}

	function print_avatars_per_screen_page()
	{
		global $import_session, $lang;

		if(SKIP_AVATAR_FILES)
		{
			echo '<tr>
	<th colspan="2" class="first last">Files disabled</th>
</tr>
<tr>
	<td colspan="2" style="text-align: center"><b>Note:</b> Copying files has been disabled</td>
</tr>';
			return;
		}

		echo '<tr>
<th colspan="2" class="first last">'.$lang->sprintf($lang->module_avatar_link, $this->board->plain_bbname).':</th>
</tr>
<tr>
<td><label for="avatarspath"> '.$lang->module_avatar_label.':</label></td>
<td width="50%"><input type="text" name="avatarspath" id="avatarspath" value="'.$import_session['avatarspath'].'" style="width: 95%;" /></td>
</tr>';
	}

	/**
	 * @param array $avatar
	 *
	 * @return bool|string
	 */
	abstract function generate_raw_filename($avatar);

	/**
	 * Generates the MyBB friendly gravatar url for an email and with a specified default
	 *
	 * @param string $email
	 * @param string $default
	 * @return string
	 */
	function get_gravatar_url($email, $default='mm')
	{
		global $mybb;

		// If user image does not exist, or is a higher rating, use the mystery man
		$email = md5($email);

		if(!$mybb->settings['maxavatardims'])
		{
			$mybb->settings['maxavatardims'] = '100x100'; // Hard limit of 100 if there are no limits
		}

		// Because Gravatars are square, hijack the width
		list($maxwidth, $maxheight) = explode("x", my_strtolower($mybb->settings['maxavatardims']));
		$maxheight = (int)$maxwidth;

		// Rating?
		$types = array('g', 'pg', 'r', 'x');
		$rating = $mybb->settings['useravatarrating'];

		if(!in_array($rating, $types))
		{
			$rating = 'g';
		}

		$s = "?s={$maxheight}&r={$rating}&d={$default}";

		return "http://www.gravatar.com/avatar/{$email}{$s}";
	}

	/**
	 * Check whether the email has an associated gravatar
	 *
	 * @param string $email
	 * @return bool
	 */
	function check_gravatar_exists($email)
	{
		$headers = @get_headers($this->get_gravatar_url($email, '404'));

		$status = 0;
		if(preg_match('#HTTP[/]1.?[0-9]{1,} ?([0-9]{3}) ?(.*)#i', $headers[0], $matches))
		{
			$status = $matches[1];
		}

		if($status >= 200 & $status < 300)
		{
			return true;
		}

		return false;
	}

	/**
	 * Generates the correct avatar name for an uploaded avatar
	 *
	 * @param array|int $user Either an user array or the user id (in the MyBB database)
	 * @param string $avatar Either the full file name or only the extension (jpg, png, ...)
	 *
	 * @return string
	 */
	function get_upload_avatar_name($user, $avatar)
	{
		global $mybb;

		if(is_array($user) && !empty($user['uid']))
		{
			$user = $user['uid'];
		}

		$user = (int)$user;

		if($user <= 0)
		{
			die('Invalid user specified for "get_upload_avatar_name"');
		}

		$ext = get_extension($avatar);

		return $mybb->settings['avataruploadpath'] . "/avatar_{$user}.{$ext}?dateline=".TIME_NOW;
	}
}


