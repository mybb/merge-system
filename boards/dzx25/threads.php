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

class DZX25_Converter_Module_Threads extends Converter_Module_Threads {
	
	var $settings = array(
			'friendly_name' => 'threads',
			'progress_column' => 'tid',
			'default_per_screen' => 1000,
	);
	
	function import()
	{
		global $import_session;

		$query = $this->old_db->query("
			SELECT
				thread.*,
				post.pid AS pid
			FROM ".OLD_TABLE_PREFIX."forum_thread AS thread
				LEFT JOIN ".OLD_TABLE_PREFIX."forum_post AS post
					ON (post.tid = thread.tid AND post.first = 1)
			ORDER BY thread.tid ASC
			LIMIT ".$this->trackers['start_threads'].", ".$import_session['threads_per_screen']
				);
		while($thread = $this->old_db->fetch_array($query))
		{
			$this->insert($thread);
		}
	}
	
	function convert_data($data)
	{
		$insert_data = array();
		
		// Discuz! values
		$insert_data['import_tid'] = $data['tid'];
		$insert_data['import_uid'] = $data['authorid'];
		$insert_data['import_firstpost'] = $data['pid'];
		$insert_data['import_poll'] = $data['tid'];
		
		$insert_data['fid'] = $this->get_import->fid($data['fid']);
		$insert_data['subject'] = encode_to_utf8(utf8_unhtmlentities($data['subject']), "forum_thread", "threads");
		if($data['typeid'])
		{
			$insert_data['prefix'] = $this->get_import->threadprefix($data['typeid']);
		}
		$insert_data['uid'] = $this->get_import->uid($data['authorid']);
		$insert_data['dateline'] = $data['dateline'];
		$insert_data['views'] = $data['views'];
		$insert_data['replies'] = $data['replies'];
		if($data['closed'] == 1)
		{
			$insert_data['closed'] = '1';
		}
		else if($data['closed'] != 0)
		{
			// A moved thread leaves a trail.
			$insert_data['closed'] = 'moved|'.$this->get_import->tid($data['closed']);
		}
		
		if($data['displayorder'] > 0)
		{
			$insert_data['sticky'] = 1;
		}
		elseif($data['displayorder'] == -1)
		{
			// In recycled bin.
			$insert_data['visible'] = -1;
		}
		elseif($data['displayorder'] == -2)
		{
			// Unapproved.
			$insert_data['visible'] = 0;
		}
		
		return $insert_data;
	}
	
	function fetch_total()
	{
		global $import_session;
		
		// Get number of threads
		if(!isset($import_session['forum_thread']))
		{
			$query = $this->old_db->simple_select("forum_thread", "COUNT(*) as count");
			$import_session['total_threads'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}
		
		return $import_session['total_threads'];
	}
}

