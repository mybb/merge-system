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

class SMF2_Converter_Module_Forumperms extends Converter_Module_Forumperms {

	var $settings = array(
		'friendly_name' => 'forum permissions',
		'progress_column' => 'id_board',
		'default_per_screen' => 1000,
	);

	var $perm2mybb = array(
			'poll_vote' => 'canvotepolls',
			'remove_own' => 'candeletethreads',
			'delete_own' => 'candeleteposts',
			'modify_own' => 'caneditposts',
			'poll_add_own' => 'canpostpolls',
			'post_attachment' => 'canpostattachments',
			'post_new' => 'canpostthreads',
			'post_reply_any' => 'canpostreplys',
			'view_attachments' => 'candlattachments'
		);

	function import()
	{
		global $import_session;

		$query = $this->old_db->query("
			SELECT p.id_group, GROUP_CONCAT(p.permission) as permissions, b.id_board
			FROM ".OLD_TABLE_PREFIX."boards b
			LEFT JOIN ".OLD_TABLE_PREFIX."board_permissions p ON (p.id_profile=b.id_profile)
			WHERE p.id_group>4 AND p.permission IN ('".implode("','", array_keys($this->perm2mybb))."')
			GROUP BY b.id_board
			LIMIT {$this->trackers['start_forumperms']}, {$import_session['forumperms_per_screen']}
		");
		while($perm = $this->old_db->fetch_array($query))
		{
			$this->insert($perm);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// SMF values
		$insert_data['fid'] = $this->get_import->fid($data['id_board']);
		$insert_data['gid'] = $this->get_import->gid($data['id_group']);

		$permissions = explode(',', $data['permissions']);
		foreach($permissions as $name)
		{
			if(!$this->perm2mybb[$name])
			{
				continue;
			}

			$insert_data[$this->perm2mybb[$name]] = 1;
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
			'id_board' => 2,
			'id_group' => 3,
			'permissions' => 'poll_vote,remove_own,delete_own,modify_own,poll_add_own,post_attachment,post_new,post_reply_any,view_attachments',
		);

		$match_data = array(
			'fid' => 10,
			'gid' => 11,
			'canvotepolls' => 1,
			'candeletethreads' => 1,
			'candeleteposts' => 1,
			'caneditposts' => 1,
			'canpostpolls' => 1,
			'canpostattachments' => 1,
			'canpostthreads' => 1,
			'canpostreplys' => 1,
			'candlattachments' => 1,
		);

		$this->assert($data, $match_data);
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of forum permissions
		if(!isset($import_session['total_forumperms']))
		{
			// Difficult query... Simply run it and call num_rows
			$query = $this->old_db->query("
				SELECT p.id_group, GROUP_CONCAT(p.permission) as permissions, b.id_board
				FROM ".OLD_TABLE_PREFIX."boards b
				LEFT JOIN ".OLD_TABLE_PREFIX."board_permissions p ON (p.id_profile=b.id_profile)
				WHERE p.id_group>4 AND p.permission IN ('".implode("','", array_keys($this->perm2mybb))."')
				GROUP BY b.id_board
			");
			$import_session['total_forumperms'] = $this->old_db->num_rows($query);
			$this->old_db->free_result($query);
		}

		return $import_session['total_forumperms'];
	}
}

?>