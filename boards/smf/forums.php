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

class SMF_Converter_Module_Forums extends Converter_Module_Forums {

	var $settings = array(
		'friendly_name' => 'forums',
		'progress_column' => 'ID_BOARD',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select("boards", "*", "", array('limit_start' => $this->trackers['start_forums'], 'limit' => $import_session['forums_per_screen']));
		while($forum = $this->old_db->fetch_array($query))
		{
			$fid = $this->insert($forum);

			// Update our internal cache
			$this->get_import->cache_fids[$forum['ID_BOARD']] = $fid;
			$this->get_import->cache_fids_f[$forum['ID_BOARD']] = $fid;
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// SMF values
		$insert_data['import_fid'] = intval($data['ID_BOARD']);
		$insert_data['name'] = encode_to_utf8($this->fix_ampersand($data['name']), "boards", "forums");
		$insert_data['description'] = encode_to_utf8($this->fix_ampersand($data['description']), "boards", "forums");

		if($data['ID_PARENT'])
		{
			$insert_data['pid'] = $this->get_import->fid_f($data['ID_PARENT']);
		}
		else
		{
			$insert_data['pid'] = $this->get_import->fid_c($data['ID_CAT']);
		}

		$insert_data['disporder'] = $data['boardOrder'];
		$insert_data['usepostcounts'] = int_to_01($data['countPosts']);

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of forums
		if(!isset($import_session['total_forums']))
		{
			$query = $this->old_db->simple_select("boards", "COUNT(*) as count");
			$import_session['total_forums'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_forums'];
	}

	/**
	 * Correctly associate any forums with their correct parent ids. This is automagically run after importing
	 * forums.
	 */
	function cleanup()
	{
		global $db;

		$query = $db->simple_select("forums", "fid", "pid != 0 AND import_fid != 0");
		while($forum = $db->fetch_array($query))
		{
			$db->update_query("forums", array('parentlist' => make_parent_list_pid($forum['fid'])), "fid='{$forum['fid']}'", 1);
		}
	}
}

?>