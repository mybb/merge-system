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

class DZX25_Converter_Module_Pollvotes extends Converter_Module_Pollvotes {
	
	var $settings = array(
			'friendly_name' => 'poll votes',
			'progress_column' => 'tid',
			'default_per_screen' => 1000,
	);
	
	var $dz_increment = false;
	
	var $cache_poll_choices = array();
	
	function import()
	{
		global $import_session;
		
		$query = $this->old_db->simple_select("forum_pollvoter", "*", "", array('order_by' => 'tid', 'order_dir' => 'asc', 'limit_start' => $this->trackers['start_pollvotes'], 'limit' => $import_session['pollvotes_per_screen']));
		while($pollvote = $this->old_db->fetch_array($query))
		{
			if(empty($pollvote['options']))
			{
				$this->increment_tracker('pollvotes');
				continue;
			}
			
			$this->dz_increment = false;
			$pollvotes = $this->dz_prepare_insert($pollvote);
			$rows = count($pollvotes);
			if($rows == 0)
			{
				$this->increment_tracker('pollvotes');
				continue;
			}
			for($i = 0; $i < $rows; $i++)
			{
				if($i == $rows - 1)
				{
					$this->dz_increment = true;
				}
				$this->insert($pollvotes[$i]);
			}
		}
	}
	
	function convert_data($data)
	{
		$insert_data = array();
		
		// Discuz! values
		$insert_data['uid'] = $this->get_import->uid($data['dz_uid']);
		$insert_data['dateline'] = $data['dateline'];
		$insert_data['voteoption'] = $data['dz_voteoption'];
		$insert_data['pid'] = $this->get_import->pollid($data['dz_tid']);
		
		return $insert_data;
	}
	
	function dz_prepare_insert($data)
	{
		$prepare_insert_data = array();
		
		$poll_options = $this->get_poll_choices($data['tid']);
		
		if(empty($poll_options))
		{
			return $prepare_insert_data;
		}
		
		$options = array_unique(array_filter(explode("\t", $data['options'])));
		foreach($options as $option)
		{
			if(isset($poll_options[$option]))
			{
				$prepare_insert_data[] = array(
						'dz_tid' => $data['tid'],
						'dz_uid' => $data['uid'],
						'dz_voteoption' => $poll_options[$option],
						'dz_dateline' => $data['dateline'],
				);
			}
		}
		
		return $prepare_insert_data;
	}
		
	/**
	 * Customized insertertion poll vote into database
	 *
	 * @param array $data The insert array going into the MyBB database
	 * @return int The new id
	 */
	public function insert($data)
	{
		global $db, $output;
		
		$this->debug->log->datatrace('$data', $data);
		
		$output->print_progress("start", $data[$this->settings['progress_column']]);
		
		// Call our currently module's process function
		$data = $this->convert_data($data);
		
		// Should loop through and fill in any values that aren't set based on the MyBB db schema or other standard default values and escape them properly
		$insert_array = $this->prepare_insert_array($data, 'pollvotes');
		
		$this->debug->log->datatrace('$insert_array', $insert_array);
		
		$db->insert_query("pollvotes", $insert_array);
		$pollvoteid = $db->insert_id();
		
		if($this->dz_increment)
		{
			$this->increment_tracker('pollvotes');
			$this->dz_increment = false;
		}
		
		$output->print_progress("end");
		
		return $pollvoteid;
	}
	
	function get_poll_choices($tid)
	{
		if(array_key_exists($tid, $this->cache_poll_choices))
		{
			return $this->cache_poll_choices[$tid];
		}
		
		$poll_choices = array();
		$id = 0;
		
		// Use a complex query to maintain poll options display order
		$query = $this->old_db->query("
			SELECT
				*
			FROM ".OLD_TABLE_PREFIX."forum_polloption
			WHERE tid = '".$tid."'
			ORDER BY displayorder ASC, polloptionid ASC
		");
		while($vote_result = $this->old_db->fetch_array($query))
		{
			$poll_choices[$vote_result['polloptionid']] = ++$id;
		}
		$this->old_db->free_result($query);
		
		$this->cache_poll_choices[$tid] = $poll_choices;
		
		return $poll_choices;
	}
	
	function fetch_total()
	{
		global $import_session;
		
		// Get number of poll votes
		if(!isset($import_session['total_pollvotes']))
		{
			$query = $this->old_db->simple_select("forum_pollvoter", "COUNT(*) as count");
			$import_session['total_pollvotes'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}
		
		return $import_session['total_pollvotes'];
	}
}


