<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2011 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class VBULLETIN3_Converter_Module_Forumperms extends Converter_Module_Forumperms {

	var $settings = array(
		'friendly_name' => 'forum permissions',
		'progress_column' => 'forumid',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select("forumpermission", "*", "", array('limit_start' => $this->trackers['start_forumperms'], 'limit' => $import_session['forumperms_per_screen']));
		while($perm = $this->old_db->fetch_array($query))
		{
			$this->insert($perm);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// vBulletin 3 values
		$insert_data['fid'] = $this->get_import->fid($data['forumid']);
		$insert_data['gid'] = $this->board->get_group_id($data['usergroupid'], array("not_multiple" => true));

		$perm_bits = array(
			"canview" => 1,
			"canviewthreads" => 2,
			"candlattachments" => 4096,
			"canpostthreads" => 16,
			"canpostreplys" => 64,
			"canpostattachments" => 8192,
			"canratethreads" => 65536,
			"caneditposts" => 128,
			"candeleteposts" => 256,
			"candeletethreads" => 512,
			"caneditattachments" => 8192,
			"canpostpolls" => 16384,
			"canvotepolls" => 32768,
			"cansearch" => 4
		);

		foreach($perm_bits as $key => $val)
		{
			if($data['forumpermission'] & $val)
			{
				$insert_data[$key] = 1;
			}
			else
			{
				$insert_data[$key] = 0;
			}
		}

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of forum permissions
		if(!isset($import_session['total_forumperms']))
		{
			$query = $this->old_db->simple_select("forumpermission", "COUNT(*) as count");
			$import_session['total_forumperms'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_forumperms'];
	}
}

?>
