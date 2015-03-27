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
			WHERE p.permission IN ('".implode("','", array_keys($this->perm2mybb))."')
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
		$insert_data['gid'] = $this->board->get_gid($data['id_group']);

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
				WHERE p.permission IN ('".implode("','", array_keys($this->perm2mybb))."')
				GROUP BY b.id_board
			");
			$import_session['total_forumperms'] = $this->old_db->num_rows($query);
			$this->old_db->free_result($query);

			// We need to run cleanup to make sure the canview perm gets merged too
			if($import_session['total_forumperms'] == 0)
			{
				$this->cleanup();
			}
		}

		return $import_session['total_forumperms'];
	}


	// We need to handle the canview permissions seperatly
	function cleanup()
	{
		global $db;

		$gcache = array();
		$gquery = $db->simple_select('usergroups', 'gid,import_gid');
		while($group = $db->fetch_array($gquery))
		{
			if(in_array($group['gid'], $this->board->groups))
			{
				$t = array_flip($this->board->groups);
				$gcache[$group['gid']] = $t[$group['gid']];
			}
			else
			{
				$gcache[$group['gid']] = $group['import_gid'];
			}
		}

		$query = $this->old_db->simple_select("boards", "id_board,member_groups");
		while($forum = $this->old_db->fetch_array($query))
		{
			$groups = explode(',', $forum['member_groups']);

			foreach($gcache as $mgid => $sgid)
			{
				// No need to change anything if we really can view this forum
				// We need to check empty as registered is "0" and "0" is in an empty array according to php
				if((in_array($sgid, $groups) && !empty($forum['member_groups'])) || $mgid == MYBB_ADMINS)
				{
					continue;
				}

				$fid = $this->get_import->fid($forum['id_board']);
				$tquery = $db->simple_select('forumpermissions', 'pid', "fid={$fid} AND gid={$mgid}");
				if($db->num_rows($tquery) == 0)
				{
					// We hadn't any permissions for this forum so simply insert one and leave everything to default
					$db->insert_query('forumpermissions', array('fid' => $fid, 'gid' => $mgid, 'canview' => 0));
				}
				else
				{
					// We had permissions so simply update them
					$db->update_query('forumpermissions', array('canview' => 0), "fid={$fid} AND gid={$mgid}");
				}
			}
		}
	}
}

?>