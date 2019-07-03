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

class DZX25_Converter_Module_Forumperms extends Converter_Module_Forumperms {
	
	var $settings = array(
			'friendly_name' => 'forum permissions',
			'progress_column' => 'fid',
			'default_per_screen' => 1000,
	);
	
	var $dz_viewperm;
	var $dz_postperm;
	var $dz_replyperm;
	var $dz_getattachperm;
	var $dz_postattachperm;
	var $dz_postimageperm;
	
	var $usergroup_perms = array();
	
	var $dz_increment = false;
	var $dz_increment_count = 0;
	
	function import()
	{
		global $import_session;
		
		$query = $this->old_db->simple_select("forum_forumfield", "*", "", array('limit_start' => $this->trackers['start_forumperms'], 'limit' => $import_session['forumperms_per_screen']));
		while($perm = $this->old_db->fetch_array($query))
		{
			$this->dz_increment = false;
			$perms_import_gids = $this->dz_prepare_insert($perm);
			$rows = count($perms_import_gids);
			for($i = 0; $i < $rows; $i++)
			{
				if($i == $rows - 1)
				{
					$this->dz_increment = true;
				}
				$perm['import_gid'] = $perms_import_gids[$i];
				$this->insert($perm);
			}
		}
	}
	
	function dz_prepare_insert($data)
	{
		$this->dz_viewperm = array();
		$this->dz_postperm = array();
		$this->dz_replyperm = array();
		$this->dz_getattachperm = array();
		$this->dz_postattachperm = array();
		$this->dz_postimageperm = array();
		
		if(!empty($data['viewperm']))
		{
			$this->dz_viewperm = array_filter(explode("\t", $data['viewperm']));
		}
		if(!empty($data['postperm']))
		{
			$this->dz_postperm = array_filter(explode("\t", $data['postperm']));
		}
		if(!empty($data['replyperm']))
		{
			$this->dz_replyperm = array_filter(explode("\t", $data['replyperm']));
		}
		if(!empty($data['getattachperm']))
		{
			$this->dz_getattachperm = array_filter(explode("\t", $data['getattachperm']));
		}
		if(!empty($data['postattachperm']))
		{
			$this->dz_postattachperm = array_filter(explode("\t", $data['postattachperm']));
		}
		if(!empty($data['postimageperm']))
		{
			$this->dz_postimageperm = array_filter(explode("\t", $data['postimageperm']));
		}
		
		$perms_import_gids = array_unique(array_merge($this->dz_viewperm, $this->dz_postperm, $this->dz_replyperm, $this->dz_getattachperm, $this->dz_postattachperm, $this->dz_postimageperm));
		
		if(empty($perms_import_gids))
		{
			$perms_import_gids[] = -1;
		}
		
		return $perms_import_gids;
	}
	
	/**
	 * Customized insert forumpermissions into database
	 *
	 * @param array $data The insert array going into the MyBB database
	 * @return int The new id
	 */
	public function insert($data)
	{
		global $db, $output;
		
		if($data['import_gid'] == -1)
		{
			$this->increment_tracker('forumperms');
			return;
		}
		
		$this->debug->log->datatrace('$data', $data);
		
		$output->print_progress("start", $data[$this->settings['progress_column']]);
		
		// Call our currently module's process function
		$data = $this->convert_data($data);
		
		// Should loop through and fill in any values that aren't set based on the MyBB db schema or other standard default values and escape them properly
		$insert_array = $this->prepare_insert_array($data, 'forumpermissions');
		
		$this->debug->log->datatrace('$insert_array', $insert_array);
		
		$fpid = $db->insert_query("forumpermissions", $insert_array);
		
		if($this->dz_increment)
		{
			$this->increment_tracker('forumperms');
		}
		
		$output->print_progress("end");
		
		return $fpid;
	}
	
	function convert_data($data)
	{
		$insert_data = array();
		
		$import_gid = $data['import_gid'];
		
		// Discuz! values.
		$insert_data['fid'] = $this->get_import->fid($data['fid']);
		$insert_data['gid'] = $this->board->get_gid(intval($import_gid));
		
		// Set usergroup's default values.
		foreach($this->usergroup_perms[$insert_data['gid']] as $perm => $perm_val)
		{
			$insert_data[$perm] = $perm_val;
		}

		// Override settings if it imports any from Discuz!.
		if(!empty($this->dz_viewperm))
		{
			$insert_data['canview'] = 0;
			$insert_data['canviewthreads'] = 0;
			if(array_search($import_gid, $this->dz_viewperm) !== false)
			{
				$insert_data['canview'] = 1;
				$insert_data['canviewthreads'] = 1;
			}
		}
		
		if(!empty($this->dz_postperm))
		{
			$insert_data['canpostthreads'] = 0;
			if(array_search($import_gid, $this->dz_postperm) !== false)
			{
				$insert_data['canpostthreads'] = 1;
			}
		}
		
		if(!empty($this->dz_replyperm))
		{
			$insert_data['canpostreplys'] = 0;
			if(array_search($import_gid, $this->dz_replyperm) !== false)
			{
				$insert_data['canpostreplys'] = 1;
			}
		}
		
		if(!empty($this->dz_getattachperm))
		{
			$insert_data['candlattachments'] = 0;
			if(array_search($import_gid, $this->dz_getattachperm) !== false)
			{
				$insert_data['candlattachments'] = 1;
			}
		}
		
		// TODO: check two times?
		if(!empty($this->dz_postimageperm))
		{
			$insert_data['canpostattachments'] = 0;
			if(array_search($import_gid, $this->dz_postimageperm) !== false)
			{
				$insert_data['canpostattachments'] = 1;
			}
		}
		if(!empty($this->dz_postattachperm))
		{
			$insert_data['canpostattachments'] = 0;
			if(array_search($import_gid, $this->dz_postattachperm) !== false)
			{
				$insert_data['canpostattachments'] = 1;
			}
		}
		
		return $insert_data;
	}
	
	function fetch_total()
	{
		global $import_session;
		
		// Get number of forums
		if(!isset($import_session['total_forumperms']))
		{
			$query = $this->old_db->simple_select("forum_forumfield", "COUNT(*) as count");
			$import_session['total_forumperms'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}
		
		return $import_session['total_forumperms'];
	}
	
	function pre_setup()
	{
		global $db;
		
		// Cache usergroup permissions
		$query = $db->simple_select("usergroups", "*");
		while($group = $db->fetch_array($query))
		{
			$this->usergroup_perms[$group['gid']] = array(
					'canview' => $group['canview'],
					'canviewthreads' => $group['canviewthreads'],
					'candlattachments' => $group['candlattachments'],
					'canpostthreads' => $group['canpostthreads'],
					'canpostreplys' => $group['canpostreplys'],
					'canpostattachments' => $group['canpostattachments'],
					'canratethreads' => $group['canratethreads'],
					'caneditposts' => $group['caneditposts'],
					'candeleteposts' => $group['candeleteposts'],
					'candeletethreads' => $group['candeletethreads'],
					'caneditattachments' => $group['caneditattachments'],
					'canviewdeletionnotice' => $group['canviewdeletionnotice'],
					'modposts' => $group['modposts'],
					'modthreads' => $group['modthreads'],
					'mod_edit_posts' => $group['mod_edit_posts'],
					'modattachments' => $group['modattachments'],
					'canpostpolls' => $group['canpostpolls'],
					'canvotepolls' => $group['canvotepolls'],
					'cansearch' => $group['cansearch'],
					);
		}
	}
	
	/**
	 * Cleanup any duplicated record of a same usergroup on the same forum.
	 */
	function cleanup()
	{
		global $db;
		
		$duplicate_pids = array();
		$query = $db->query("
			SELECT
				GROUP_CONCAT(pid) as pids
			FROM
				".TABLE_PREFIX."forumpermissions
			GROUP BY
				fid,
				gid
			HAVING
				COUNT(fid) > 1
				AND COUNT(gid) > 1
				");
		while($forum = $db->fetch_array($query))
		{
			$duplicate_pids[] = $forum['pids'];
		}
		
		foreach($duplicate_pids as $pids)
		{
			$query = $db->simple_select("forumpermissions", "*", "pid IN({$pids})");
			$perm_merge = $db->fetch_array($query);
			while($perm = $db->fetch_array($query))
			{
				foreach($perm as $key => $value)
				{
					if($perm[$key] == 'pid' || $perm[$key] == 'fid' || $perm[$key] == 'gid')
					{
						continue;
					}
					if($perm[$value] == 1)
					{
						$perm_merge[$key] = 1;
					}
				}
			}
			$pids = explode(",", $pids);
			foreach($pids as $pid)
			{
				if($pid == $perm_merge['pid'])
				{
					$db->replace_query("forumpermissions", $perm_merge);
					continue;
				}
				$db->delete_query("forumpermissions", "pid={$pid}");
			}
		}
		
		parent::cleanup();
	}
}

