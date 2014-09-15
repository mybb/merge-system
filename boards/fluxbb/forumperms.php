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

class FLUXBB_Converter_Module_Forumperms extends Converter_Module_Forumperms {

	var $settings = array(
		'friendly_name' => 'forum permissions',
		'progress_column' => 'forum_id',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;
		
		$query = $this->old_db->simple_select("forum_perms", "*", "", array('limit_start' => $this->trackers['start_forumperms'], 'limit' => $import_session['forumperms_per_screen']));
		while($perm = $this->old_db->fetch_array($query))
		{
			$this->insert($perm);
		}
	}
	
	function convert_data($data)
	{
		$insert_data = array();
		
		// fluxBB values
		$insert_data['fid'] = $this->get_import->fid_f($data['forum_id']);
		$insert_data['gid'] = $this->board->get_gid($data['group_id']);
		$insert_data['canpostthreads'] = $data['post_topics'];
		$insert_data['canpostreplys'] = $data['post_replies'];
		$insert_data['canview'] = $data['read_forum'];
		
		return $insert_data;
	}
	
	function fetch_total()
	{
		global $import_session;
		
		// Get number of forum permissions
		if(!isset($import_session['total_forumperms']))
		{
			$query = $this->old_db->simple_select("forum_perms", "COUNT(*) as count");
			$import_session['total_forumperms'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}
		
		return $import_session['total_forumperms'];
	}
}

?>