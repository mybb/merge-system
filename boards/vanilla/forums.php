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

class VANILLA_Converter_Module_Forums extends Converter_Module_Forums {

	var $settings = array(
		'friendly_name' => 'forums',
		'progress_column' => 'forum_id',
		'default_per_screen' => 1000,
	);

	function pre_setup()
	{
		global $import_session, $db;

		// Vanilla has only "forums", so we need a parent category where we put everything to avoid loosing of threads
		if(!isset($import_session['parent_fid']))
		{
			$cat = array(
				"name"			=> "Vanilla imported forums",
				"type"			=> "c",
				"description"	=> "This forums were imported from your Vanilla installation",
				"pid"			=> 0,
				"parentlist"	=> "1",
				"rules"			=> "",
				"active"		=> 1,
				"open"			=> 1,
			);
			// No "input", so no need to escape
			$import_session['parent_fid'] = $db->insert_query("forums", $cat);
		}
	}

	function import()
	{
		global $import_session, $db;

		$query = $this->old_db->simple_select("category", "*", "CategoryID>0", array('limit_start' => $this->trackers['start_forums'], 'limit' => $import_session['forums_per_screen']));
		while($forum = $this->old_db->fetch_array($query))
		{
			$fid = $this->insert($forum);
		}
	}

	function convert_data($data)
	{
		global $import_session;

		$insert_data = array();

		// Vanilla Values
		$insert_data['import_fid'] = intval($data['CategoryID']);
		$insert_data['name'] = encode_to_utf8($this->fix_ampersand($data['Name']), "category", "forums");
		$insert_data['description'] = encode_to_utf8($this->bbcode_parser->convert($data['Description']), "category", "forums");
		$insert_data['disporder'] = $data['Sort'];
		$insert_data['open'] = $data['AllowDiscussions'];

		// The forum has a direct parent (we ignore the "root" category)
		if($data['ParentCategoryID'] > 0)
		{
			$insert_data['import_pid'] = $data['ParentCategoryID'];
		}
		// Otherwise use the vanilla category
		else
		{
			$insert_data['pid'] = $import_session['parent_fid'];
		}

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of forums
		if(!isset($import_session['total_forums']))
		{
			$query = $this->old_db->simple_select("category", "COUNT(*) as count", "CategoryID>0");
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