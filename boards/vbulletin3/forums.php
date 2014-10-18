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

class VBULLETIN3_Converter_Module_Forums extends Converter_Module_Forums {

	var $settings = array(
		'friendly_name' => 'forums',
		'progress_column' => 'forumid',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session, $db;

		$query = $this->old_db->simple_select("forum", "*", "", array('limit_start' => $this->trackers['start_forums'], 'limit' => $import_session['forums_per_screen'], 'order_by' => 'parentid', 'order_dir' => 'asc'));
		while($forum = $this->old_db->fetch_array($query))
		{
			$fid = $this->insert($forum);

			// Update parent list.
			if($forum['parentid'] == '-1')
			{
				$db->update_query("forums", array('parentlist' => $fid), "fid = '{$fid}'");
			}
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// vBulletin 3 values
		$insert_data['import_fid'] = $data['forumid'];
		$insert_data['name'] = encode_to_utf8($this->fix_ampersand($data['title']), "forum", "forums");
		$insert_data['description'] = encode_to_utf8($this->fix_ampersand($data['description']), "forum", "forums");
		$insert_data['disporder'] = $data['displayorder'];
		$insert_data['password'] = $data['password'];
		if($data['defaultsortfield'] == 'lastpost')
		{
			$data['defaultsortfield'] = '';
		}
		$insert_data['defaultsortby'] = $data['defaultsortfield'];
		$insert_data['defaultsortorder'] = $data['defaultsortorder'];

		// We have a category
		if($data['parentid'] == '-1')
		{
			$insert_data['type'] = 'c';
			$insert_data['import_fid'] = $data['forumid'];
		}
		// We have a forum
		else
		{
			$insert_data['linkto'] = $data['link'];
			$insert_data['type'] = 'f';
			$insert_data['import_pid'] = $data['parentid'];
		}

		$bitwise = array(
			'active' => 1,
			'open' => 2,
			'allowmycode' => 64,
			'allowimgcode' => 128,
			'allowhtml' => 256,
			'allowsmilies' => 512,
			'allowpicons' => 1024,
			'allowtratings' => 2048,
			'usepostcounts' => 4096,
			'overridestyle' => 32768,
			'showinjump' => 65536,
		);

		foreach($bitwise as $column => $bit)
		{
			if($data['options'] & $bit)
			{
				$insert_data[$column] = 1;
			}
			else
			{
				$insert_data[$column] = 0;
			}
		}

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of forums
		if(!isset($import_session['total_forums']))
		{
			$query = $this->old_db->simple_select("forum", "COUNT(*) as count");
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

?>