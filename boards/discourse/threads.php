<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 *
 * Discourse Thread (Topic) Converter
 * Discourse stores topics in the `topics` table. The first post of each topic
 * is a row in `posts` with post_number = 1. We import topics and first posts
 * together.
 * Private messages are topics with archetype = 'private_message'.
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class DISCOURSE_Converter_Module_Threads extends Converter_Module_Threads {

	var $settings = array(
		'friendly_name' => 'threads',
		'progress_column' => 'id',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;

		// Import only regular topics (not private messages)
		$query = $this->old_db->simple_select("topics", "*", "archetype = 'regular' AND deleted_at IS NULL", array('order_by' => 'id', 'order_dir' => 'ASC', 'limit_start' => $this->trackers['start_threads'], 'limit' => $import_session['threads_per_screen']));
		while($thread = $this->old_db->fetch_array($query))
		{
			$this->insert($thread);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		$insert_data['import_tid'] = $data['id'];
		$insert_data['fid'] = $this->get_import->fid($data['category_id']);
		$insert_data['subject'] = encode_to_utf8($data['title'], "topics", "threads");
		$insert_data['uid'] = $this->get_import->uid($data['user_id']);
		$insert_data['import_uid'] = $data['user_id'];
		$insert_data['dateline'] = strtotime($data['created_at']);
		$insert_data['views'] = $data['views'];
		$insert_data['replies'] = $data['posts_count'] > 0 ? $data['posts_count'] - 1 : 0;

		// Visibility
		$insert_data['visible'] = ($data['visible'] == 't' || $data['visible'] === true) ? 1 : 0;

		// Sticky / pinned
		if(!empty($data['pinned_at']))
		{
			$insert_data['sticky'] = 1;
		}

		// Closed
		if($data['closed'] == 't' || $data['closed'] === true)
		{
			$insert_data['closed'] = 1;
		}

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		if(!isset($import_session['total_threads']))
		{
			$query = $this->old_db->simple_select("topics", "COUNT(*) as count", "archetype = 'regular' AND deleted_at IS NULL");
			$import_session['total_threads'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_threads'];
	}
}
