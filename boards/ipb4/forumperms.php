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

class IPB4_Converter_Module_Forumperms extends Converter_Module_Forumperms {

	var $settings = array(
		'friendly_name' => 'forum permissions',
		'progress_column' => 'perm_id',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select("core_permission_index", "*", "app='forums' AND perm_type='forum'", array('limit_start' => $this->trackers['start_forumperms'], 'limit' => $import_session['forumperms_per_screen']));
		while($perm = $this->old_db->fetch_array($query))
		{
			$this->process_permission($perm);
		}
	}

	function process_permission($data)
	{
		$fid = $this->get_import->fid($data['perm_type_id']);

		$perms = array(
			"perm_view"	=> "canview",
			"perm_2"	=> "canviewthreads",
			"perm_3"	=> "canpostthreads",
			"perm_4"	=> "canpostreplys",
			"perm_5"	=> "canpostattachments",
			"perm_6"	=> "candlattachments"
		);

		$groups = array();
		$query = $this->old_db->simple_select("core_groups", "g_id");
		while($gid = $this->old_db->fetch_field($query, "g_id"))
		{
			$groups[$gid] = $this->board->get_gid($gid);
		}

		foreach($perms as $perm => $operm)
		{
			if($data[$perm] != '*')
			{
				$perm_split = explode(',', trim($data[$perm], ","));
			}
			foreach($groups as $ogid => $gid)
			{
				// All permissions are on (global)
				if($data[$perm] == '*' || in_array($ogid, $perm_split))
				{
					$new_perms[$gid][$operm] = 1;
				}
				else
				{
					$new_perms[$gid][$operm] = 0;
				}
			}
		}

		$this->debug->log->datatrace('$new_perms', $new_perms);

		if(!empty($new_perms))
		{
			foreach($new_perms as $gid => $perm2)
			{
				$perm_array = $perm2;
				$perm_array['gid'] = $gid;
				$perm_array['fid'] = $fid;

				$this->debug->log->datatrace('$perm_array', $perm_array);

				$this->insert($perm_array);
			}
		}
	}

	function convert_data($data)
	{
		// Nothing to do here, they're converted above
		return $data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of forum permissions
		if(!isset($import_session['total_forumperms']))
		{
			$query = $this->old_db->simple_select("core_permission_index", "COUNT(*) as count", "app='forums' AND perm_type='forum'");
			$import_session['total_forumperms'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_forumperms'];
	}
}

?>
