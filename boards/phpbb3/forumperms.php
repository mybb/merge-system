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

class PHPBB3_Converter_Module_Forumperms extends Converter_Module_Forumperms {

	var $settings = array(
		'friendly_name' => 'forum permissions',
		'progress_column' => 'fid',
		'default_per_screen' => 1000,
	);

	var $convert_val = array(
			'caneditposts' => 'f_edit',
			'candeleteposts' => 'f_delete',
			'caneditattachments' => 'f_attach',
			'canpostpolls' => 'f_poll',
			'canvotepolls' => 'f_vote',
			'canpostthreads' => 'f_post',
			'canpostreplys' => 'f_post',
			'candlattachments' => 'f_download',
			'canpostattachments' => 'f_attach',
			'canviewthreads' => 'f_read',
			'canview' => 'f_read',
			'cansearch' => 'f_search',
		);

	var $role_cache = array();
	var $role_perm_cache = array();
	var $option_cache = array();
	var $perm_cache = array();

	function pre_setup($cache=true)
	{
		$query = $this->old_db->simple_select("acl_options", "auth_option_id, auth_option", "auth_option IN ('".implode("','", $this->convert_val)."')");
		while($auth = $this->old_db->fetch_array($query))
		{
			$this->option_cache[$auth['auth_option_id']] = $auth['auth_option'];
		}
		$this->old_db->free_result($query);

		$query = $this->old_db->simple_select("acl_roles_data", "DISTINCT(role_id)", "auth_option_id IN ('".implode("','", array_keys($this->option_cache))."')");
		while($id = $this->old_db->fetch_field($query, "role_id"))
		{
			$this->role_cache[] = $id;
		}
		$this->old_db->free_result($query);

		if($cache === true)
		{
			// Ignore newly registered and bots - otherwise we'd have multiple rows for one forum and one group
			$query = $this->old_db->simple_select("groups", "group_id", "group_id NOT IN(3,6,7)");
			$groups = array();
			while($gid = $this->old_db->fetch_field($query, "group_id"))
			{
				$groups[] = $gid;
			}

			// Only forums
			$query = $this->old_db->simple_select("forums", "forum_id", "forum_type=1");
			while($fid = $this->old_db->fetch_field($query, "forum_id"))
			{
				foreach($groups as $gid)
				{
					foreach($this->convert_val as $perm)
					{
						$this->perm_cache[$fid][$gid][$perm] = 0;
					}
				}
			}
		}
	}

	function import()
	{
		global $import_session;

		// Ignores categorys, newly registered and bots - otherwise we'd have duplicated entries
		$query = $this->old_db->query("
			SELECT g.* FROM ".OLD_TABLE_PREFIX."acl_groups g
			LEFT JOIN ".OLD_TABLE_PREFIX."forums f ON (f.forum_id=g.forum_id)
			WHERE g.group_id NOT IN(3,6,7) AND g.forum_id > 0 AND f.forum_type=1 AND (auth_option_id IN ('".implode("','", array_keys($this->option_cache))."') OR auth_role_id IN ('".implode("','", $this->role_cache)."'))
			LIMIT {$this->trackers['start_forumperms']}, {$import_session['forumperms_per_screen']}
		");
		while($perm = $this->old_db->fetch_array($query))
		{
			$this->process_permissions($perm);
		}

		foreach($this->perm_cache as $fid => $temp)
		{
			foreach($temp as $gid => $perms)
			{
				$perms['fid'] = $fid;
				$perms['gid'] = $gid;
				$this->insert($perms);
			}
		}
	}

	function process_permissions($data)
	{
		if($data['auth_option_id'] > 0)
		{
			// Single permission
			$perm = $this->option_cache[$data['auth_option_id']];
			$this->perm_cache[$data['forum_id']][$data['group_id']][$perm] = $data['auth_setting'];
		}
		else
		{
			// Role permission -> get their permissions
			$perms = $this->get_role_options($data['auth_role_id']);
			foreach($perms as $name => $value)
			{
				$this->perm_cache[$data['forum_id']][$data['group_id']][$name] = $value;
			}
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// phpBB 3 values
		$insert_data['fid'] = $this->get_import->fid($data['fid']);
		$insert_data['gid'] = $this->board->get_gid($data['gid']);

		foreach($this->convert_val as $mybb_column => $phpbb_column)
		{
			$insert_data[$mybb_column] = $data[$phpbb_column];
		}

		// Yeah, this looks very very dirty and yeah it is
		// But we need to modify our trackers to avoid useless redirects
		// we increment our tracker about the number of inserted permissions
		// -1 as it get's incremented by one in the insert function called below
		// -2 for gid and fid
		$increment = count($data)-1-2;
		$this->increment_tracker("forumperms", $increment);

		return $insert_data;
	}

	function get_role_options($id)
	{
		if(isset($this->role_perm_cache[$id]))
		{
			return $this->role_perm_cache[$id];
		}

		$query = $this->old_db->simple_select("acl_roles_data", "*", "role_id='{$id}' AND auth_option_id IN ('".implode("','", array_keys($this->option_cache))."')");
		$perms = array();
		while($auth = $this->old_db->fetch_array($query))
		{
			$perms[$this->option_cache[$auth['auth_option_id']]] = $auth['auth_setting'];
		}
		$this->role_perm_cache[$id] = $perms;
		return $perms;
	}

	function fetch_total()
	{
		global $import_session;

		$this->pre_setup(false);

		// Get number of forum permissions
		if(!isset($import_session['total_forumperms']))
		{
			$query = $this->old_db->query("SELECT g.* FROM ".OLD_TABLE_PREFIX."acl_groups g
				LEFT JOIN ".OLD_TABLE_PREFIX."forums f ON (f.forum_id=g.forum_id)
				WHERE g.group_id NOT IN(3,6,7) AND g.forum_id > 0 AND f.forum_type=1 AND (auth_option_id IN ('".implode("','", array_keys($this->option_cache))."') OR auth_role_id IN ('".implode("','", $this->role_cache)."'))");
			$import_session['total_forumperms'] = $this->old_db->num_rows($query);
			$this->old_db->free_result($query);
		}

		return $import_session['total_forumperms'];
	}
}

