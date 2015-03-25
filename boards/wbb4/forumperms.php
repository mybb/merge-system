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

class WBB4_Converter_Module_Forumperms extends Converter_Module_Forumperms {

	var $settings = array(
		'friendly_name' => 'forum permissions',
		'progress_column' => 'boardID',
		'default_per_screen' => 1000,
	);

	var $convert_val = array(
		"canViewBoard" => "canview",
		"canReadThread" => "canviewthreads",
		"canStartThread" => "canpostthreads",
		"canReplyThread" => "canpostreplys",
		"canStartPoll" => "canpostpolls",
		"canVotePoll" => "canvotepolls",
		"canUploadAttachment" => "canpostattachments",
		"canDownloadAttachment" => "candlattachments",
		"canDeleteOwnPost" => "candeleteposts",
		"canEditOwnPost" => "caneditposts",
	);

	var $group_cache = array();
	var $perm_cache = array();

	function import()
	{
		global $import_session;

		$query = $this->old_db->query("SELECT *
				FROM ".WCF_PREFIX."acl_option_to_group g
				LEFT JOIN ".WCF_PREFIX."acl_option o ON(o.optionID=g.optionID)
				WHERE o.optionName IN ('".implode("','", array_keys($this->convert_val))."')
				ORDER BY objectID, groupID, optionName");
		while($perm = $this->old_db->fetch_array($query))
		{
			$this->perm_cache[$perm['objectID']][$perm['groupID']][$perm['optionName']] = $perm['optionValue'];
		}

		foreach($this->perm_cache as $fid => $pt)
		{
			foreach($pt as $gid => $perms)
			{
				$perms['groupID'] = $gid;
				$perms['boardID'] = $fid;
				if($gid == 1)
				{
					// Those permissions are added to all groups. To avoid any wrong counters they're handled seperatly
					$this->insert_all($perms);
					$this->increment_tracker('forumperms');
				}
				else
				{
					$this->insert($perms);
				}
			}
		}
	}

	function convert_data($data)
	{
		$insert_data['fid'] = $this->get_import->fid($data['boardID']);
		$insert_data['gid'] = $this->board->get_gid($data['groupID']);

		foreach($this->convert_val as $wbb => $mybb)
		{
			$insert_data[$mybb] = $data[$wbb];
		}

		return $insert_data;
	}

	function insert_all($data)
	{
		global $db;

		if(empty($this->group_cache))
		{
			$query = $db->simple_select("usergroups", "gid,import_gid");
			while($group = $this->old_db->fetch_array($query))
			{
				if(in_array($group['gid'], $this->board->groups))
				{
					$t = array_flip($this->board->groups);
					$this->group_cache[$group['gid']] = $t[$group['gid']];
				}
				else
				{
					$this->group_cache[$group['gid']] = $group['import_gid'];
				}
			}
		}

		// We'll exclude groups which have their own permission set for this forum
		$query = $this->old_db->query("SELECT groupID
				FROM ".WCF_PREFIX."acl_option_to_group g
				LEFT JOIN ".WCF_PREFIX."acl_option o ON (o.optionID=g.optionID)
				WHERE objectID={$data['boardID']} AND groupID>1 AND o.optionName IN ('".implode("','", array_keys($this->convert_val))."')");
		$groups = $this->group_cache;
   		while($gid = $this->old_db->fetch_field($query, "groupID"))
		{
			foreach($groups as $mybb => $wbb)
			{
				if($wbb == $gid)
				    unset($groups[$mybb]);
			}
		}

		// Now convert our fid and the permissions
		$insert_data['fid'] = $this->get_import->fid($data['boardID']);
		foreach($this->convert_val as $wbb => $mybb)
		{
			$insert_data[$mybb] = $data[$wbb];
		}

		// Yep, we're using MyBB's groups here to apply this permissions also to them
		foreach(array_keys($groups) as $gid)
		{
			$tperm = $insert_data;
			$tperm['gid'] = $gid;

			// Should loop through and fill in any values that aren't set based on the MyBB db schema or other standard default values and escape them properly
			$insert_array = $this->prepare_insert_array($tperm);
			$db->insert_query("forumpermissions", $insert_array);
		}
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of forum permissions
		if(!isset($import_session['total_forumperms']))
		{
			$query = $this->old_db->query("SELECT groupID
				FROM ".WCF_PREFIX."acl_option_to_group g
				LEFT JOIN ".WCF_PREFIX."acl_option o ON(o.optionID=g.optionID)
				WHERE o.optionName IN ('".implode("','", array_keys($this->convert_val))."')
				GROUP BY groupID,objectID");
			$import_session['total_forumperms'] = $this->old_db->num_rows($query);
			$this->old_db->free_result($query);
		}

		return $import_session['total_forumperms'];
	}
}

?>