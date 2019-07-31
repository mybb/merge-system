<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2019 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class DZUCENTER_Converter_Module_Avatars extends Converter_Module_Avatars
{
	var $settings = array(
			'friendly_name' => 'avatars',
			'progress_column' => 'uid',
			'default_per_screen' => 20,
	);
	
	function get_avatar_path()
	{
		$uploadspath = "/PATH_TO_UCENTER/data/avatar/";
		return $uploadspath;
	}
	
	function import()
	{
		global $import_session;
		
		$query = $this->old_db->simple_select("members", "uid", "", array('order_by' => 'uid', 'order_dir' => 'ASC', 'limit_start' => $this->trackers['start_avatars'], 'limit' => $import_session['avatars_per_screen']));
		while($user = $this->old_db->fetch_array($query))
		{
			$file = $this->get_avatar_file($user['uid']);
			if($file === false)
			{
				$this->increment_tracker('avatars');
				continue;
			}
			$user['avatar'] = $file['filename'];
			$user['size'] = $file['size'];
			$this->insert($user);
		}
	}
	
	function convert_data($data)
	{
		$insert_data = array();
		
		// Discuz! UCenter values
		$insert_data['uid'] = $this->get_import->uid($data['uid']);
		
		$insert_data['avatar'] = $this->get_upload_avatar_name($insert_data['uid'], $data['avatar']);
		$img_size = getimagesize($data['avatar']);
		$insert_data['avatardimensions'] = "{$img_size[0]}|{$img_size[1]}";
		$insert_data['avatartype'] = AVATAR_TYPE_UPLOAD;
		
		return $insert_data;
	}
	
	function after_insert($unconverted_data, $converted_data, $aid)
	{
		parent::after_insert($unconverted_data, $converted_data, $aid);
		
		if($converted_data['avatartype'] != AVATAR_TYPE_UPLOAD || SKIP_AVATAR_FILES) {
			return;
		}
		
		// Check if this avatar is uploaded.
		if(substr($converted_data['avatar'], 0, 2) == "./" || substr($converted_data['avatar'], 0, 3) == "../")
		{
			$converted_data['avatar'] = MYBB_ROOT.$converted_data['avatar'];
		}
		$converted_data['avatar'] = my_substr($converted_data['avatar'], 0, strpos($converted_data['avatar'], '?'));
		if(!file_exists($converted_data['avatar']))
		{
			return;
		}
		
		// Resize the avatar, if its size exceeds.
		global $mybb, $db, $lang;
		
		if(!$mybb->settings['maxavatardims'])
		{
			$mybb->settings['maxavatardims'] = '100x100'; // Hard limit of 100 if there are no limits
		}
		
		// Hijack the width.
		list($maxwidth, $maxheight) = explode("x", my_strtolower($mybb->settings['maxavatardims']));
		$maxheight = (int)$maxwidth;
		
		$avatarpath = dirname($converted_data['avatar']);
		$filename = my_substr($converted_data['avatar'], 0, strpos($converted_data['avatar'], '?'));
		$filename = my_substr($filename, strrpos($filename, '/') === false ? 0 : strrpos($filename, '/') + 1);
		$img_dimensions = @getimagesize($avatarpath."/".$filename);
		if(($maxwidth && $img_dimensions[0] > $maxwidth) || ($maxheight && $img_dimensions[1] > $maxheight))
		{
			// Generating avatar thumbnail.
			require_once MYBB_ROOT."inc/functions_image.php";
			$thumbnail = generate_thumbnail($avatarpath."/".$filename, $avatarpath, $filename, $maxheight, $maxwidth);
			if($thumbnail['filename'])
			{
				$img_size = getimagesize($avatarpath."/".$filename);
				$avatardimensions = "{$img_size[0]}|{$img_size[1]}";
				
				// Update database.
				$db->update_query("users", array("avatardimensions" => $avatardimensions), "uid='{$converted_data['uid']}'");
			}
			else if($thumbnail['code'] != 4)
			{
				$this->board->set_error_notice_in_progress("import_avatars: error found when generating thumbnail for uid #{$aid}, may be caused by a damaged image file. The original avater file is still transferred.");
			}
		}
	}
	
	function fetch_total()
	{
		global $import_session;
		
		// Get number of users, we have to check the avatar file's existence for each user.
		if(!isset($import_session['total_avatars']))
		{
			$query = $this->old_db->simple_select("members", "COUNT(*) as count");
			$import_session['total_avatars'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}
		
		return $import_session['total_avatars'];
	}
	
	/**
	 * Check if any size, 'big', 'middle' and 'small', of avatar file of the given uid exists.
	 * @param string|int $uid The Discuz! uid.
	 * @return string|bool The size of existing avatar, or false no avatar file exists.
	 */
	function get_avatar_file($uid)
	{
		global $import_session;
		
		$size_array = array('big', 'middle', 'small');
		$file = array();
		foreach($size_array as $size)
		{
			$filename = $import_session['avatarspath'] . $this->dz_get_avatar($uid, $size);
			if(file_exists($filename))
			{
				$file['filename'] = $filename;
				$file['size'] = $size;
				break;
			}
		}
		if(empty($file))
		{
			return false;
		}
		return $file;
	}
	
	function generate_raw_filename($avatar)
	{
		return $this->dz_get_avatar($avatar['uid'], $avatar['size']);
	}
	
	/**
	 * This function comes from Discuz! UCenter server part.
	 * @param string|int $uid The Discuz! uid.
	 * @param string $size The size of requested avatar, can be 'big', 'middle' and 'small'.
	 * @return string The path contain the avatar, relative to UCenter's avatar dierectory.
	 */
	function dz_get_avatar($uid, $size = 'middle') {
		$size = in_array($size, array('big', 'middle', 'small')) ? $size : 'middle';
		$uid = abs(intval($uid));
		$uid = sprintf("%09d", $uid);
		$dir1 = substr($uid, 0, 3);
		$dir2 = substr($uid, 3, 2);
		$dir3 = substr($uid, 5, 2);
		return $dir1.'/'.$dir2.'/'.$dir3.'/'.substr($uid, -2)."_avatar_{$size}.jpg";
	}
}
