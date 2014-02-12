<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 *
 * $Id: forumperms.php 4394 2010-12-14 14:38:21Z ralgith $
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class IPB2_Converter_Module_Forumperms extends Converter_Module_Forumperms {

	var $settings = array(
		'friendly_name' => 'forum permissions',
		'progress_column' => 'id',
		'default_per_screen' => 1000,
	);

	function pre_setup()
	{
		global $import_session;

		if(empty($import_session['forumperms_groups']))
		{
			$query = $this->old_db->query("
				SELECT p.perm_id, g.g_perm_id, g.g_id
				FROM ".OLD_TABLE_PREFIX."forum_perms p
				LEFT JOIN ".OLD_TABLE_PREFIX."groups g ON (p.perm_id=g.g_perm_id)
			");
			while($permgroup = $this->old_db->fetch_array($query))
			{
				$import_session['forumperms_groups'][$permgroup['g_perm_id']] = $permgroup;
			}
			$this->old_db->free_result($query);
			$import_session['forumperms_groups_count'] = count($import_session['forumperms_groups']);
		}
	}

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select("forums", "permission_array,id", "", array('limit_start' => $this->trackers['start_forumperms'], 'limit' => $import_session['forumperms_per_screen']));
		while($perm = $this->old_db->fetch_array($query))
		{
			$this->process_permission($perm);
		}
	}

	function process_permission($data)
	{
		$permission_array = unserialize(stripslashes($data['permission_array']));
		$this->debug->log->datatrace('$permission_array', $permission_array);

		foreach($permission_array as $key => $permission)
		{
			$this->debug->log->trace3("\$key: {$key} \$permission: {$permission}");
			// All permissions are on (global)
			if($permission == '*')
			{
				continue;
			}
			else
			{
				$perm_split = explode(',', $permission);
				foreach($perm_split as $key2 => $gid)
				{
					$new_perms[$this->board->get_group_id($gid, array("not_multiple" => true))][$key] = 1;
				}
			}
		}

		$this->debug->log->datatrace('$new_perms', $new_perms);

		if(!empty($new_perms))
		{
			foreach($new_perms as $gid => $perm2)
			{
				foreach($permission_array as $key => $value)
				{
					if(!array_key_exists($key, $perm2))
					{
						$perm2[$key] = 0;
					}
				}
				$perm_array = $perm2;
				$perm_array['gid'] = $gid;

				$this->debug->log->datatrace('$perm_array', $perm_array);

				$this->insert($perm_array);
			}
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// Invision Power Board 2 values
		$insert_data['fid'] = $this->get_import->fid($data['id']);
		$insert_data['gid'] = $data['gid'];
		$insert_data['canpostthreads'] = yesno_to_int($data['start_perms']);
		$insert_data['canpostreplys'] = yesno_to_int($data['reply_perms']);
		$insert_data['candlattachments'] = yesno_to_int($data['download_perms']);
		$insert_data['canpostattachments'] = yesno_to_int($data['upload_perms']);
		$insert_data['canviewthreads'] = yesno_to_int($data['read_perms']);
		$insert_data['canview'] = yesno_to_int($data['show_perms']);

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of forum permissions
		if(!isset($import_session['total_forumperms']))
		{
			$query = $this->old_db->simple_select("forums", "COUNT(*) as count");
			$import_session['total_forumperms'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_forumperms'];
	}
}

?>