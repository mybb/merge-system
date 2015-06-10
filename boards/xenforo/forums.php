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

class XENFORO_Converter_Module_Forums extends Converter_Module_Forums {

	var $settings = array(
		'friendly_name' => 'forums',
		'progress_column' => 'forumid',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session, $db;
		
		$query = $this->old_db->simple_select("node", "*", "", array('limit_start' => $this->trackers['start_forums'], 'limit' => $import_session['forums_per_screen'], 'order_by' => 'node_id', 'order_dir' => 'asc'));
		while($forum = $this->old_db->fetch_array($query))
		{
			// TODO: fetch additional infos from xf_forum
			$fid = $this->insert($forum);
			
			// Update parent list.
			if($forum['parent_node_id'] == '0')
			{
				$db->update_query("forums", array('parentlist' => $fid), "fid = '{$fid}'");
			}
		}
	}
	
	function convert_data($data)
	{
		$insert_data = array();
		
		// Xenforo 1 values
		$insert_data['import_fid'] = $data['node_id'];
		$insert_data['name'] = encode_to_utf8($this->fix_ampersand($data['title']), "node", "forums");
		$insert_data['description'] = encode_to_utf8($this->fix_ampersand($data['description']), "node", "forums");		
		$insert_data['disporder'] = $data['display_order'];
		
		// We have a category
		if($data['parent_node_id'] == '0')
		{
			$insert_data['type'] = 'c';
		}
		// We have a forum
		else
		{
			$insert_data['type'] = 'f';
			$insert_data['import_pid'] = $data['parent_node_id'];
		}
		
		return $insert_data;
	}
	
	function fetch_total()
	{
		global $import_session;
		
		// Get number of forums
		if(!isset($import_session['total_forums']))
		{
			$query = $this->old_db->simple_select("node", "COUNT(*) as count");
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

		$query = $db->query("
			SELECT f.fid, f2.fid as updatefid, f.import_fid
			FROM ".TABLE_PREFIX."forums f
			LEFT JOIN ".TABLE_PREFIX."forums f2 ON (f2.import_fid=f.import_pid)
			WHERE f.import_pid != '0' AND f.pid = '0'
		");
		while($forum = $db->fetch_array($query))
		{
			$db->update_query("forums", array('pid' => $forum['updatefid'], 'parentlist' => make_parent_list($forum['import_fid'])), "fid='{$forum['fid']}'", 1);
		}
	}
}

