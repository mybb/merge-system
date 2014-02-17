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

class PHPBB2_Converter_Module_Forumperms extends Converter_Module_Forumperms {

	var $settings = array(
		'friendly_name' => 'forum permissions',
		'progress_column' => 'fid',
		'default_per_screen' => 1000,
	);

	var $convert_val = array(
			'caneditposts' => 'auth_edit',
			'candeleteposts' => 'auth_delete',
			'caneditattachments' => 'auth_attachments',
			'canpostpolls' => 'auth_pollcreate',
			'canvotepolls' => 'auth_vote',
			'canpostthreads' => 'auth_post',
			'canpostreplys' => 'auth_reply',
			'candlattachments' => 'auth_attachments',
			'canpostattachments' => 'auth_attachments',
			'canviewthreads' => 'auth_view',
			'canview' => 'auth_view'
		);

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select("forums", "forum_id,auth_view,auth_read,auth_post,auth_reply,auth_edit,auth_delete,auth_sticky,auth_announce,auth_vote,auth_pollcreate,auth_attachments", "", array('limit_start' => $this->trackers['start_forumperms'], 'limit' => $import_session['forumperms_per_screen']));
		while($perm = $this->old_db->fetch_array($query))
		{
			$this->process_permission_matrix($perm);
		}

		$this->process_permissions();
	}

	function process_permission_matrix($perm)
	{
		$this->debug->log->datatrace('$perm', $perm);

		foreach($perm as $column => $value)
		{
			$this->debug->log->trace3("\$column: {$column} \$value: {$value}");

			if($column == "forum_id")
			{
				continue;
			}

			switch($value)
			{
				case 0:
					$this->permissions[$perm['forum_id']][1][$column] = 1;
					$this->permissions[$perm['forum_id']][2][$column] = 1;
					$this->permissions[$perm['forum_id']][3][$column] = 1;
					$this->permissions[$perm['forum_id']][4][$column] = 1;
					break;
				case 1:
					$this->permissions[$perm['forum_id']][2][$column] = 1;
					$this->permissions[$perm['forum_id']][4][$column] = 1;
					$this->permissions[$perm['forum_id']][3][$column] = 1;
					break;
				case 2:
				case 3:
					$this->permissions[$perm['forum_id']][4][$column] = 1;
					break;
				case 4:
					$this->permissions[$perm['forum_id']][3][$column] = 1;
					$this->permissions[$perm['forum_id']][4][$column] = 1;
					break;
				default:
					continue;
			}
			$this->debug->log->datatrace($this->permissions[$perm['forum_id']], $this->permissions[$perm['forum_id']]);
		}
		$this->debug->log->datatrace('$this->permissions', $this->permissions);
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

		// phpBB 2 values
		$insert_data['fid'] = $this->get_import->fid_f($data['fid']);
		$insert_data['gid'] = $this->board->get_group_id($data['gid'], array("not_multiple" => true));

		foreach($this->convert_val as $mybb_column => $phpbb_column)
		{
			if(!$data['columns'][$phpbb_column])
			{
				$data['columns'][$phpbb_column] = 0;
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
			$query = $this->old_db->simple_select("forums", "COUNT(*) as count");
			$import_session['total_forumperms'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_forumperms'];
	}
}

?>