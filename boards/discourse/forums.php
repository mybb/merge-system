<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 *
 * Discourse Forum (Category) Converter
 * Discourse uses categories instead of forums. Categories can be nested.
 * Top-level categories (parent_category_id IS NULL) become MyBB categories.
 * Sub-categories become MyBB forums.
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class DISCOURSE_Converter_Module_Forums extends Converter_Module_Forums {

	var $settings = array(
		'friendly_name' => 'forums',
		'progress_column' => 'id',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session, $db;

		// Import categories ordered by parent so parents exist before children
		$query = $this->old_db->simple_select("categories", "*", "", array('order_by' => 'COALESCE(parent_category_id, 0)', 'order_dir' => 'asc', 'limit_start' => $this->trackers['start_forums'], 'limit' => $import_session['forums_per_screen']));
		while($category = $this->old_db->fetch_array($query))
		{
			$fid = $this->insert($category);

			// Update parent list for categories
			if(empty($category['parent_category_id']))
			{
				$db->update_query("forums", array('parentlist' => $fid), "fid = '{$fid}'");
			}
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		$insert_data['import_fid'] = $data['id'];
		$insert_data['name'] = encode_to_utf8($data['name'], "categories", "forums");
		$insert_data['description'] = encode_to_utf8($data['description'], "categories", "forums");
		$insert_data['disporder'] = $data['position'];

		// Top-level category (no parent) → MyBB category
		if(empty($data['parent_category_id']))
		{
			$insert_data['type'] = 'c';
		}
		// Sub-category → MyBB forum
		else
		{
			$insert_data['type'] = 'f';
			$insert_data['import_pid'] = $data['parent_category_id'];
		}

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		if(!isset($import_session['total_forums']))
		{
			$query = $this->old_db->simple_select("categories", "COUNT(*) as count");
			$import_session['total_forums'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_forums'];
	}

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

		parent::cleanup();
	}
}
