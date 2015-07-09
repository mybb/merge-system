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

class WBB4_Converter_Module_Avatars extends Converter_Module_Avatars {

	var $settings = array(
		'friendly_name' => 'avatars',
		'progress_column' => 'userID',
		'default_per_screen' => 20,
	);

	function get_avatar_path()
	{
		$query = $this->old_db->simple_select(WCF_PREFIX."application", "domainName,domainPath", "isPrimary='1'");
		$data = $this->old_db->fetch_array($query);
		return "http://".$data['domainName'].$data['domainPath']."wcf/images/avatars/";
	}

	function import()
	{
		global $import_session;

		$query = $this->old_db->query("SELECT u.userID, u.email, u.enableGravatar, u.avatarID, a.avatarExtension, a.width, a.height, a.fileHash
			FROM ".WCF_PREFIX."user u
			LEFT JOIN ".WCF_PREFIX."user_avatar a ON (a.avatarID=u.avatarID)
			WHERE u.avatarID > 0 OR u.enableGravatar = 1
			LIMIT {$this->trackers['start_avatars']}, {$import_session['avatars_per_screen']}");
		while($avatar = $this->old_db->fetch_array($query))
		{
			$this->insert($avatar);
		}
	}

	function convert_data($data)
	{
		global $mybb;

		$insert_data = array();

		// MyBB 1.8 values
		$insert_data['uid'] = $this->get_import->uid($data['userID']);

		if($data['enableGravatar'])
		{
			$insert_data['avatartype'] = AVATAR_TYPE_GRAVATAR;
			$insert_data['avatar'] = $this->get_gravatar_url($data['email']);

			if(!$mybb->settings['maxavatardims'])
			{
				$mybb->settings['maxavatardims'] = '100x100'; // Hard limit of 100 if there are no limits
			}

			list($maxwidth, $maxheight) = explode("x", my_strtolower($mybb->settings['maxavatardims']));
			$this->dimension = "{$maxheight}|{$maxwidth}";
		}
		else
		{
			$insert_data['avatar'] = $this->get_upload_avatar_name($insert_data['uid'], $data['avatarExtension']);
			$insert_data['avatartype'] = AVATAR_TYPE_UPLOAD;
			$insert_data['avatardimensions'] = "{$data['height']}|{$data['width']}";
		}

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of users with avatar
		if(!isset($import_session['total_avatars']))
		{
			$query = $this->old_db->simple_select(WCF_PREFIX."user", "COUNT(*) as count", "avatarID > 0 OR enableGravatar != ''");
			$import_session['total_avatars'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_avatars'];
	}

	function generate_raw_filename($avatar)
	{
		$dir = substr($avatar['fileHash'], 0, 2);
		return "{$dir}/{$avatar['avatarID']}-{$avatar['fileHash']}.{$avatar['avatarExtension']}";
	}
}
