<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 *
 * Discourse Poll Converter
 * Discourse has a built-in poll plugin. Polls are stored in the `polls` table
 * with options in `poll_options` and votes in `poll_votes`.
 * Note: Polls are an optional Discourse feature. This converter skips silently
 * if polls table doesn't exist.
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class DISCOURSE_Converter_Module_Polls extends Converter_Module_Polls {

	var $settings = array(
		'friendly_name' => 'polls',
		'progress_column' => 'id',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session, $db;

		// Check if polls table exists (optional Discourse feature)
		if(!$this->old_db->table_exists("polls"))
		{
			$import_session['total_polls'] = 0;
			return;
		}

		$query = $this->old_db->simple_select("polls", "*", "", array('limit_start' => $this->trackers['start_polls'], 'limit' => $import_session['polls_per_screen']));
		while($poll = $this->old_db->fetch_array($query))
		{
			$pid = $this->insert($poll);

			// Update thread poll reference
			$tid = $this->get_import->tid($this->get_topic_id_from_post($poll['post_id']));
			if($tid)
			{
				$db->update_query("threads", array('poll' => $pid), "tid = '{$tid}'");
			}
		}
	}

	function convert_data($data)
	{
		global $db;

		$insert_data = array();

		$insert_data['import_pid'] = $data['id'];
		$insert_data['tid'] = $this->get_import->tid($this->get_topic_id_from_post($data['post_id']));
		$insert_data['question'] = $data['name'];
		$insert_data['dateline'] = strtotime($data['created_at']);
		$insert_data['timeout'] = !empty($data['close_at']) ? strtotime($data['close_at']) - strtotime($data['created_at']) : 0;
		$insert_data['multiple'] = ($data['type'] == 1) ? 1 : 0; // 0=single, 1=multiple
		$insert_data['public'] = $data['public'] == 't' ? 1 : 0;

		// Get poll options
		$options = '';
		$votes = '';
		$opt_count = 0;
		$vote_count = 0;
		$seperator = '';

		$opt_query = $this->old_db->simple_select("poll_options", "*", "poll_id = '{$data['id']}'", array('order_by' => 'id', 'order_dir' => 'ASC'));
		while($opt = $this->old_db->fetch_array($opt_query))
		{
			$options .= $seperator.$db->escape_string($opt['html']);
			$votes .= $seperator.$opt['anonymous_votes'];
			$opt_count++;
			$vote_count += $opt['anonymous_votes'];
			$seperator = '||~|~||';
		}
		$this->old_db->free_result($opt_query);

		$insert_data['options'] = $options;
		$insert_data['votes'] = $votes;
		$insert_data['numoptions'] = $opt_count;
		$insert_data['numvotes'] = $vote_count;

		return $insert_data;
	}

	function get_topic_id_from_post($post_id)
	{
		$query = $this->old_db->simple_select("posts", "topic_id", "id = '{$post_id}'", array('limit' => 1));
		$tid = $this->old_db->fetch_field($query, 'topic_id');
		$this->old_db->free_result($query);
		return $tid;
	}

	function fetch_total()
	{
		global $import_session;

		if(!isset($import_session['total_polls']))
		{
			if(!$this->old_db->table_exists("polls"))
			{
				$import_session['total_polls'] = 0;
			}
			else
			{
				$query = $this->old_db->simple_select("polls", "COUNT(*) as count");
				$import_session['total_polls'] = $this->old_db->fetch_field($query, 'count');
				$this->old_db->free_result($query);
			}
		}

		return $import_session['total_polls'];
	}
}
