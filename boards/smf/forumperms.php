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

class SMF_Converter_Module_Forumperms extends Converter_Module_Forumperms {

	var $settings = array(
		'friendly_name' => 'forum permissions',
		'progress_column' => 'ID_BOARD',
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
			SELECT ID_GROUP, ID_BOARD, GROUP_CONCAT(permission) as permissions
			FROM ".OLD_TABLE_PREFIX."board_permissions
			WHERE ID_GROUP != '-1' AND ID_BOARD > 0
			GROUP BY ID_GROUP, ID_BOARD
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
		$insert_data['fid'] = $this->get_import->fid($data['ID_BOARD']);
		$insert_data['gid'] = $this->board->get_gid($data['ID_GROUP']);

		$permissions = explode(',', $data['permissions']);
		foreach($permissions as $name)
		{
			if (!$this->perm2mybb[$name])
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
			$query = $this->old_db->simple_select("board_permissions", "COUNT(*) as count", "ID_GROUP != '-1' AND ID_BOARD > 0");
			$import_session['total_forumperms'] = $this->old_db->fetch_field($query, 'count');
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

		$query = $this->old_db->simple_select("boards", "ID_BOARD,memberGroups");
		while($forum = $this->old_db->fetch_array($query))
		{
			$groups = explode(',', $forum['memberGroups']);

			foreach($gcache as $mgid => $sgid)
			{
				// No need to change anything if we really can view this forum
				if(in_array($sgid, $groups) || $mgid == MYBB_ADMINS)
				{
					continue;
				}

				$fid = $this->get_import->fid($forum['ID_BOARD']);
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