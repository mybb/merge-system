<?php
/**
 * MyBB 1.6
 * Copyright © 2009 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
  * License: http://www.mybb.com/about/license
 *
 * $Id: usergroups.php 4394 2010-12-14 14:38:21Z ralgith $
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class FLUXBB_Converter_Module_Usergroups extends Converter_Module_Usergroups {

	var $settings = array(
		'friendly_name' => 'usergroups',
		'progress_column' => 'g_id',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session, $db;

		// Get only non-staff groups.
		$query = $this->old_db->simple_select("groups", "*", "g_id > 4", array('limit_start' => $this->trackers['start_usergroups'], 'limit' => $import_session['usergroups_per_screen']));
		while($group = $this->old_db->fetch_array($query))
		{
			$gid = $this->insert($group);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// fluxBB values
		$insert_data['import_gid'] = $data['g_id'];
		$insert_data['title'] = $data['g_title'];
		$insert_data['canview'] = $data['g_read_board'];
		$insert_data['canpostthreads'] = $data['g_post_topics'];
		$insert_data['canpostreplys'] = $data['g_post_replies'];
		$insert_data['caneditposts'] = $data['g_edit_posts'];
		$insert_data['candeleteposts'] = $data['g_delete_posts'];
		$insert_data['candeletethreads'] = $data['g_delete_topics'];
		$insert_data['cansearch'] = $data['g_search'];
		$insert_data['canviewmemberlist'] = $data['g_search_users'];

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of usergroups
		if(!isset($import_session['total_usergroups']))
		{
			$query = $this->old_db->simple_select("groups", "COUNT(*) as count", "g_id > 4");
			$import_session['total_usergroups'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_usergroups'];
	}
}

?>