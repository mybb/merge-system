<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2019 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class DZX25_Converter_Module_Forums extends Converter_Module_Forums {
	
	var $settings = array(
			'friendly_name' => 'forums',
			'progress_column' => 'fid',
			'default_per_screen' => 1000,
	);
	
	function import()
	{
		global $import_session;

		$query = $this->old_db->query("
			SELECT
				forum.*,
				forumfield.description AS description,
				forumfield.redirect AS redirect,
				forumfield.password AS password,
				forumfield.rules AS rules
			FROM ".OLD_TABLE_PREFIX."forum_forum AS forum
				LEFT JOIN ".OLD_TABLE_PREFIX."forum_forumfield AS forumfield
					ON (forumfield.fid = forum.fid)
			ORDER BY forum.fid ASC
			LIMIT ".$this->trackers['start_forums'].", ".$import_session['forums_per_screen']
				);
		while($forum = $this->old_db->fetch_array($query))
		{
			$this->insert($forum);
		}
	}
	
	function convert_data($data)
	{
		$insert_data = array();
		
		// Discuz! values
		$insert_data['import_fid'] = $data['fid'];
		$insert_data['import_pid'] = $data['fup'];
		$insert_data['name'] = encode_to_utf8($data['name'], "forum_forum", "forums");
		$insert_data['description'] = encode_to_utf8($data['description'], "forum_forum", "forums");
		$insert_data['linkto'] = $data['redirect'];
		
		if($data['type'] == 'group')
		{
			$insert_data['type'] = 'c';
		}
		else
		{
			$insert_data['type'] = 'f';
		}
		
		$insert_data['pid'] = $data['fup'];
		
		$insert_data['disporder'] = $data['displayorder'];
		$insert_data['active'] = $data['status'];
		
		$insert_data['allowhtml'] = $data['allowhtml'];
		$insert_data['allowmycode'] = $data['allowbbcode'];
		$insert_data['allowsmilies'] = $data['allowsmilies'];
		$insert_data['allowimgcode'] = $data['allowimgcode'];
		$insert_data['allowvideocode'] = $data['allowmediacode'];
		
		$insert_data['password'] = $data['password'];
		$insert_data['rules'] = $data['rules'];
		
		return $insert_data;
	}
	
	function fetch_total()
	{
		global $import_session;
		
		// Get number of forums
		if(!isset($import_session['total_forums']))
		{
			$query = $this->old_db->simple_select("forum_forum", "COUNT(*) as count");
			$import_session['total_forums'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}
		
		return $import_session['total_forums'];
	}
	
	/**
	 * Make 'c' type forum's parentlist and 'f' type forum's pid.
	 */
	function finish()
	{
		global $db;
		
		// 'c' type forum.
		$query = $db->simple_select("forums", "fid,import_fid", "type = 'c' AND import_fid != 0 AND import_pid = 0");
		while($forum = $db->fetch_array($query))
		{
			$db->update_query("forums", array('parentlist' => $forum['fid']), "fid='{$forum['fid']}'", 1);
		}
		$db->free_result($query);
		
		// 'f' type forum.
		$query = $db->simple_select("forums", "fid,import_pid", "type = 'f' AND import_fid != 0 AND import_pid != 0");
		while($forum = $db->fetch_array($query))
		{
			$pid = $this->get_import->fid($forum['import_pid']);
			$db->update_query("forums", array('pid' => $pid), "fid='{$forum['fid']}'", 1);
		}
		$db->free_result($query);
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
		
		parent::cleanup();
	}
}

