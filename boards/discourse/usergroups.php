<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 *
 * Discourse Usergroup Converter
 * Discourse uses trust levels + automatic groups. We map trust levels 3 and 4
 * to custom MyBB groups, and create groups for moderator/admin sets.
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class DISCOURSE_Converter_Module_Usergroups extends Converter_Module_Usergroups {

	var $settings = array(
		'friendly_name' => 'usergroups',
		'progress_column' => 'id',
		'default_per_screen' => 100,
	);

	function import()
	{
		global $import_session;

		// Import non-automatic Discourse groups (custom groups created by admins)
		// Automatic groups are trust level groups which we handle separately below
		$query = $this->old_db->simple_select("groups", "*", "automatic = FALSE", array('limit_start' => $this->trackers['start_usergroups'], 'limit' => $import_session['usergroups_per_screen']));
		while($group = $this->old_db->fetch_array($query))
		{
			$this->insert($group);
		}

		// Always create MyBB groups for Discourse trust levels that aren't in the default map
		// Trust Level 3 (Regular) and Trust Level 4 (Leader)
		$this->insert_trust_level_group(3, "Regular");
		$this->insert_trust_level_group(4, "Leader");
	}

	function insert_trust_level_group($tl, $name)
	{
		global $db;

		// Check if already imported
		$query = $db->simple_select("usergroups", "gid", "import_gid = 'tl{$tl}'");
		if($db->num_rows($query) > 0)
		{
			return;
		}

		$insert_data = array(
			'import_gid' => 'tl'.$tl,
			'title' => $this->board->bbname.' '.$name,
			'canview' => 1,
			'canviewthreads' => 1,
			'canpostthreads' => 1,
			'canpostreplys' => 1,
			'canpostattachments' => 1,
			'caneditposts' => 1,
			'candeleteposts' => 1,
			'canpostpolls' => 1,
			'canvotepolls' => 1,
			'canusepms' => 1,
			'cansearch' => 1,
			'canviewprofiles' => 1,
		);

		$this->insert($insert_data);
	}

	function convert_data($data)
	{
		$insert_data = array();

		$insert_data['import_gid'] = $data['id'];
		$insert_data['title'] = !empty($data['title']) ? $data['title'] : $data['name'];

		// Default permissions for imported groups
		$insert_data['canview'] = 1;
		$insert_data['canviewthreads'] = 1;
		$insert_data['canpostthreads'] = 1;
		$insert_data['canpostreplys'] = 1;
		$insert_data['canpostattachments'] = 1;
		$insert_data['caneditposts'] = 1;
		$insert_data['candeleteposts'] = 1;
		$insert_data['canpostpolls'] = 1;
		$insert_data['canvotepolls'] = 1;
		$insert_data['canusepms'] = 1;
		$insert_data['cansearch'] = 1;
		$insert_data['canviewprofiles'] = 1;

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		if(!isset($import_session['total_usergroups']))
		{
			$query = $this->old_db->simple_select("groups", "COUNT(*) as count", "automatic = FALSE");
			$import_session['total_usergroups'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);

			// +2 for trust level 3 and 4 groups we always create
			$import_session['total_usergroups'] += 2;
		}

		return $import_session['total_usergroups'];
	}
}
