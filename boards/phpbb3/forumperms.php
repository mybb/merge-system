<?php
/**
 * MyBB 1.6
 * Copyright © 2009 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
  * License: http://www.mybb.com/about/license
 *
 * $Id: forumperms.php 4394 2010-12-14 14:38:21Z ralgith $
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
			SELECT g.group_id, g.forum_id, g.auth_option_id, g.auth_setting, o.auth_option
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
		$insert_data['gid'] = $this->get_import->gid($data['gid']);
		
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
	
	function test()
	{
		$this->get_import->cache_fids = array(
			2 => 10,
		);
		
		$this->get_import->cache_gids = array(
			3 => 11,
		);
		
		$data = array(
			'fid' => 2,
			'gid' => 3,
			'columns' => array(
				'f_edit' => 1,
				'f_delete' => 1,
				'f_attach' => 1,
				'f_poll' => 1,
				'f_vote' => 1,
				'f_post' => 1,
				'f_download' => 1,
				'f_attach' => 1,
				'f_read' => 1,
				'f_search' => 1,
			),
		);
		
		$match_data = array(
			'fid' => 10,
			'gid' => 11,
			'caneditposts' => 1,
			'candeleteposts' => 1,
			'caneditattachments' => 1,
			'canpostpolls' => 1,
			'canvotepolls' => 1,
			'canpostthreads' => 1,
			'canpostreplys' => 1,
			'candlattachments' => 1,
			'canpostattachments' => 1,
			'canviewthreads' => 1,
			'canview' => 1,
			'cansearch' => 1,
		);
		
		$this->assert($data, $match_data);
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
				WHERE o.is_local=1 AND o.auth_option IN ('".implode("','", $this->convert_val)."')
			");
			$import_session['total_forumperms'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}
		
		return $import_session['total_forumperms'];
	}
}

?>