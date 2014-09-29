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

	function import()
	{
		global $import_session;

		$query = $this->old_db->query("
			SELECT g.*, o.auth_option
			FROM ".OLD_TABLE_PREFIX."acl_groups g
			LEFT JOIN ".OLD_TABLE_PREFIX."acl_options o ON (g.auth_option_id=o.auth_option_id)
			WHERE g.auth_option_id > 0 AND o.auth_option IN ('".implode("','", $this->convert_val)."')
			LIMIT {$this->trackers['start_forumperms']}, {$import_session['forumperms_per_screen']}
		");
		while($perm = $this->old_db->fetch_array($query))
		{
			$this->debug->log->datatrace('$perm', $perm);

			$this->permissions[$perm['forum_id']][$perm['group_id']][$perm['auth_option']] = $perm['auth_setting'];
		}

		$this->process_permissions();
	}

	function process_permissions()
	{
		$this->debug->log->datatrace('$this->permissions', $this->permissions);

		if(is_array($this->permissions))
		{
			foreach($this->permissions as $fid => $groups)
			{
				foreach($groups as $gid => $columns)
				{
					$perm = array(
						'fid' => $fid,
						'gid' => $gid,
						'columns' => $columns,
					);

					$this->debug->log->datatrace('$perm', $perm);

					// Yeah, this looks very very dirty and yeah it is
					// But we need to modify our trackers to avoid useless redirects
					// we increment our tracker about the number of inserted permissions
					// -1 as it get's incremented by one in the insert function called below
					$increment = count($columns)-1;
					$this->increment_tracker("forumperms", $increment);

					$this->insert($perm);
				}
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
			if(!$data['columns'][$phpbb_column])
			{
				$data['columns'][$phpbb_column] = 0;
			}
			else
			{
				$data['columns'][$phpbb_column] = 1;
			}

			$insert_data[$mybb_column] = $data['columns'][$phpbb_column];
		}

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of forum permissions
		if(!isset($import_session['total_forumperms']))
		{
			$query = $this->old_db->query("
				SELECT COUNT(*) as count
				FROM ".OLD_TABLE_PREFIX."acl_groups g
				LEFT JOIN ".OLD_TABLE_PREFIX."acl_options o ON (g.auth_option_id=o.auth_option_id)
				WHERE g.auth_option_id > 0 AND o.auth_option IN ('".implode("','", $this->convert_val)."')
			");
			$import_session['total_forumperms'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_forumperms'];
	}
}

?>