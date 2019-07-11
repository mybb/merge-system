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

class DZX25_Converter_Module_Moderators extends Converter_Module_Moderators {

	var $settings = array(
		'friendly_name' => 'moderators',
		'progress_column' => 'fid',
		'default_per_screen' => 1000,
	);
	
	// Override Merge System's default values.
	public $default_values = array(
			'fid' => 0,
			'id' => 0,
			'isgroup' => 0,
			'caneditposts' => 1,
			'cansoftdeleteposts' => 1,
			'canrestoreposts' => 1,
			'candeleteposts' => 0,
			'cansoftdeletethreads' => 1,
			'canrestorethreads' => 1,
			'candeletethreads' => 0,
			'canviewips' => 0,
			'canviewunapprove' => 1,
			'canviewdeleted' => 1,
			'canopenclosethreads' => 1,
			'canstickunstickthreads' => 1,
			'canapproveunapprovethreads' => 1,
			'canapproveunapproveposts' => 1,
			'canapproveunapproveattachs' => 1,
			'canmanagethreads' => 1,
			'canmanagepolls' => 1,
			'canpostclosedthreads' => 1,
			'canmovetononmodforum' => 1,
			'canusecustomtools' => 1,
			'canmanageannouncements' => 1,
			'canmanagereportedposts' => 1,
			'canviewmodlog' => 1,
	);
	
	function __construct($converter_class)
	{
		parent::__construct($converter_class);
		if(defined("DXZ25_CONVERTER_MODERS_INVALIDATE_ALL_PERMS") && DXZ25_CONVERTER_MODERS_INVALIDATE_ALL_PERMS)
		{
			foreach($this->default_values as $key => $value)
			{
				if($key == 'fid' || $key == 'id' || $key == 'isgroup')
				{
					continue;
				}
				$this->default_values[$key] = 0;
			}
		}
	}

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select("forum_forumfield", "fid,moderators", "", array('limit_start' => $this->trackers['start_moderators'], 'limit' => $import_session['moderators_per_screen']));
		while($moderators = $this->old_db->fetch_array($query))
		{
			if(empty($moderators['moderators']))
			{
				$this->increment_tracker('moderators');
				continue;
			}
			
			$this->dz_increment = false;
			$moderators_array = $this->dz_prepare_insert($moderators);
			$rows = count($moderators_array);
			if($rows == 0)
			{
				$this->increment_tracker('moderators');
				continue;
			}
			for($i = 0; $i < $rows; $i++)
			{
				if($i == $rows - 1)
				{
					$this->dz_increment = true;
				}
				$this->insert($moderators_array[$i]);
			}
		}
	}
	
	function dz_prepare_insert($data)
	{
		$prepare_insert_data = array();
		
		$usernames = array_unique(array_filter(explode("\t", $data['moderators'])));
		foreach($usernames as $username)
		{
			$uid = $this->board->dz_get_uid($username);
			if($uid !== false)
			{
				$prepare_insert_data[] = array(
						'id' => $uid,
						'fid' => $data['fid'],
						'isgroup' => 0,
				);
			}
		}
		
		return $prepare_insert_data;
	}

	/**
	 * Customized insertion moderator into database
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
		$insert_array = $this->prepare_insert_array($data, 'moderators');
		
		$this->debug->log->datatrace('$insert_array', $insert_array);
		
		$db->insert_query("moderators", $insert_array);
		$mid = $db->insert_id();
		
		if($this->update_tracker)
		{
			$this->increment_tracker('moderators');
			$this->update_tracker = false;
		}
		
		$output->print_progress("end");
		
		return $mid;
	}
	
	function convert_data($data)
	{
		$insert_data = array();

		// Discuz! values
		// Essential fields are already handled in the prepare function.

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of moderators
		if(!isset($import_session['total_moderators']))
		{
			$query = $this->old_db->simple_select("forum_forumfield", "COUNT(*) as count");
			$import_session['total_moderators'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_moderators'];
	}
}

