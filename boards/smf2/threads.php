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

class SMF2_Converter_Module_Threads extends Converter_Module_Threads {

	var $settings = array(
		'friendly_name' => 'threads',
		'progress_column' => 'id_topic',
		'default_per_screen' => 1000,
	);

	var $get_attachment_count_cache = array();

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select("topics", "*", "", array('limit_start' => $this->trackers['start_threads'], 'limit' => $import_session['threads_per_screen']));
		while($thread = $this->old_db->fetch_array($query))
		{
			$this->insert($thread);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// SMF values
		$insert_data['import_tid'] = $data['id_topic'];
		$insert_data['sticky'] = $data['is_sticky'];
		$insert_data['fid'] = $this->get_import->fid($data['id_board']);

		$first_post = $this->board->get_post($data['id_first_msg']);
		$insert_data['dateline'] = $first_post['poster_time'];
		$insert_data['subject'] = encode_to_utf8(utf8_unhtmlentities($first_post['subject']), "messages", "threads");

		$insert_data['import_poll'] = $data['id_poll'];
		$insert_data['uid'] = $this->get_import->uid($data['id_member_started']);
		$insert_data['import_uid'] = $data['id_member_started'];
		$insert_data['import_firstpost'] = $data['id_first_msg'];
		$insert_data['views'] = $data['num_views'];
		$insert_data['closed'] = $data['locked'];
		if($insert_data['closed'] == "no")
		{
			$insert_data['closed'] = '';
		}

		$insert_data['attachmentcount'] = $this->get_attachment_count($data['id_topic']);

		return $insert_data;
	}

	function get_attachment_count($tid)
	{
		if(array_key_exists($tid, $this->get_attachment_count_cache))
		{
			return $this->get_attachment_count_cache[$tid];
		}

		$pids = '';
		$comma = '';
		$count = 0;

		// TODO: Rewrite this down into cacheable function
		$query = $this->old_db->simple_select("messages", "id_msg", "id_topic='{$tid}'");
		while($post = $this->old_db->fetch_array($query))
		{
			$pids .= $comma.$post['id_msg'];
			$comma = ', ';
		}
		$this->old_db->free_result($query);

		if($pids)
		{
			$query = $this->old_db->simple_select("attachments", "COUNT(*) as numattachments", "id_msg IN($pids)");
			$count = $this->old_db->fetch_field($query, 'numattachments');
			$this->old_db->free_result($query);
		}

		$this->get_attachment_count_cache[$tid] = $count;

		return $count;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of threads
		if(!isset($import_session['total_threads']))
		{
			$query = $this->old_db->simple_select("topics", "COUNT(*) as count");
			$import_session['total_threads'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_threads'];
	}
}

?>