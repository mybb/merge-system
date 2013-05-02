<?php
/**
 * MyBB 1.6
 * Copyright 2009 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 * $Id$
 * Modified for Mingle Forums 1.0 by http://www.communityplugins.com
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class MINGLE_Converter_Module_Forums extends Converter_Module_Forums {

	var $settings = array(
		'friendly_name' => 'forums',
		'progress_column' => 'id',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session, $db;
		
		$query = $this->old_db->simple_select("forum_forums", "*", "1=1", array('order_by' => 'id', 'order_dir' => 'ASC', 'limit_start' => $this->trackers['start_forums'], 'limit' => $import_session['forums_per_screen']));
		while($forum = $this->old_db->fetch_array($query))
		{
			$forum['import_pid'] = $forum['parent_id'];
			$fid = $this->insert($forum);
			
			// TODO: forum subscriptions? use wp_options.option_name = mf_forum_subscriptions_{fid} and lookup users based on list of emails

		}
	}
	
	function convert_data($data)
	{
		$insert_data = array();
		
		// Mingle Values
		$insert_data['import_fid'] = intval($data['id']);
		$insert_data['import_pid'] = intval($data['parent_id']);
		$insert_data['name'] = encode_to_utf8($data['name'], "forum_forums", "forums");
		$insert_data['description'] = encode_to_utf8($this->bbcode_parser->convert($data['description']), "forum_forums", "forums");
		$insert_data['disporder'] = $data['sort'];

		$insert_data['type'] = 'f';
		
	
		// TODO: last post data?
		
		return $insert_data;
	}
	
	function fetch_total()
	{
		global $import_session;
		
		// Get number of forums
		if(!isset($import_session['total_forums']))
		{
			$query = $this->old_db->simple_select("forum_forums", "COUNT(*) as count");
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
/*
		$query = $db->query("
			SELECT f.fid, f2.fid as updatefid, f.import_fid
			FROM ".TABLE_PREFIX."forums f
			LEFT JOIN ".TABLE_PREFIX."forums f2 ON (f2.import_fid=f.import_pid)
			WHERE f.import_pid != 0 AND f.pid = 0
		");
	*/	
		$query = $db->query("
			SELECT f1.fid, f2.fid AS updatefid
			FROM mybb_forums f1
			INNER JOIN mybb_forums f2 ON f1.import_pid = f2.import_fid
			WHERE f2.type = 'c'
		");
		
		while($forum = $db->fetch_array($query))
		{
			$db->update_query("forums", array('pid' => $forum['updatefid'], 'parentlist' => $forum['updatefid'].",".$forum['fid']), "fid='{$forum['fid']}'", 1);
		}
		
		//TODO: fix for subforums and original query does not seem to work at all
	
	}
}

?>
