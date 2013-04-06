<?php
/**
 * MyBB 1.6
 * Copyright 2009 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 * $Id$
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class BBPRESS_Converter_Module_Forums extends Converter_Module_Forums {

	var $settings = array(
		'friendly_name' => 'forums',
		'progress_column' => 'forum_id',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session, $db;
		
		$query = $this->old_db->simple_select("forums", "*", "", array('limit_start' => $this->trackers['start_forums'], 'limit' => $import_session['forums_per_screen']));
		while($forum = $this->old_db->fetch_array($query))
		{
			$fid = $this->insert($forum);
			
			$forum['forum_type'] = '1';
			$oldfid = $forum['forum_id'];
			$query2 = $this->old_db->simple_select("meta", "*", "object_id = '{$oldfid}' AND meta_key = 'forum_is_category'");
			$forum2 = $this->old_db->fetch_array($query2);
			if($forum2['meta_value'] == '1')
			{
				$forum['forum_type'] = '0';
			}
			
			// Update parent list.
			if($forum['forum_type'] == '0')
			{
				$db->update_query("forums", array('parentlist' => $fid), "fid = '{$fid}'");
			}
		}
	}
	
	function convert_data($data)
	{
		$insert_data = array();
		
		// bbPress Values
		$insert_data['import_fid'] = intval($data['forum_id']);
		$insert_data['name'] = encode_to_utf8($data['forum_name'], "forums", "forums");
		$insert_data['description'] = encode_to_utf8($this->bbcode_parser->convert($data['forum_desc']), "forums", "forums");
		$insert_data['disporder'] = $data['forum_order'];
		$insert_data['linkto'] = '';
		$insert_data['import_pid'] = $data['forum_parent'];
		
		$oldfid =$data['forum_id'];
		$query2 = $this->old_db->simple_select("meta", "*", "object_id = '{$oldfid}' AND meta_key = 'forum_is_category'");
		$forum2 = $this->old_db->fetch_array($query2);

		// We have a category
		if($forum2['meta_value'] == '1')
		{
			$insert_data['type'] = 'c';
		}
		// We have a forum
		else
		{
			$insert_data['type'] = 'f';
		}
		
		// TODO: last post data?
		
		return $insert_data;
	}
	
	function fetch_total()
	{
		global $import_session;
		
		// Get number of forums
		if(!isset($import_session['total_forums']))
		{
			$query = $this->old_db->simple_select("forums", "COUNT(*) as count");
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
			WHERE f.import_pid != 0 AND f.pid = 0
		");
		while($forum = $db->fetch_array($query))
		{
			$db->update_query("forums", array('pid' => $forum['updatefid'], 'parentlist' => make_parent_list($forum['import_fid'])), "fid='{$forum['fid']}'", 1);
		}
	}
}

?>