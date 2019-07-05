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

class DZX25_Converter_Module_Threadprefixes extends Converter_Module {
	
	var $settings = array(
			'friendly_name' => 'threadprefixes',
			'progress_column' => 'typeids',
			'encode_table' => 'forum_threadclass',
			'default_per_screen' => 2000,
	);

	public $default_values = array(
			'import_pids' => '',
			'forums' => '-1',
			'groups' => '-1',
	);
	
	public $binary_fields = array(
	);
	
	public $integer_fields = array(
	);
	
	var $pid_found = false;
	
	var $all_forums = array();
	var $all_groups = array();
	
	var $mod_usergroups = array(MYBB_ADMINS, MYBB_SMODS, MYBB_MODS);
	
	var $dz_forums_threadclasses = array();
	
	function import()
	{
		global $import_session;
		
		// Get distinct threadclasses in Discuz!
		$query = $this->old_db->query("
			SELECT
				GROUP_CONCAT(typeid) AS typeids,
				name,
				GROUP_CONCAT(fid) AS fids,
				GROUP_CONCAT(moderators) AS mods,
				COUNT(name) AS count
			FROM ".OLD_TABLE_PREFIX."forum_threadclass
			GROUP BY name
			LIMIT ".$this->trackers['start_threadprefixes'].", ".$import_session['threadprefixes_per_screen']
				);
		while($threadprefix = $this->old_db->fetch_array($query))
		{
			$this->insert($threadprefix);
		}
	}
	
	public function insert($data)
	{
		global $db, $output;
		
		$this->debug->log->datatrace('$data', $data);
		
		$output->print_progress("start", $data[$this->settings['progress_column']]);
		
		// Call our currently module's process function
		$data = $this->convert_data($data);
		
		// Should loop through and fill in any values that aren't set based on the MyBB db schema or other standard default values and escape them properly
		$insert_array = $this->prepare_insert_array($data, 'threadprefixes');
		
		$this->debug->log->datatrace('$insert_array', $insert_array);
		
		$pid = 0;
		if($this->pid_found)
		{
			// Storing the mybbufid of a userfield to be updated.
			$pid = $insert_array['pid'];
			unset($insert_array['prefix']);
			unset($insert_array['displaystyle']);
		}
		if(isset($insert_array['pid']))
		{
			unset($insert_array['pid']);
		}
		
		
		if($this->pid_found)
		{
			// Update a record.
			$db->update_query("threadprefixes", $insert_array, "pid = '{$pid}'");
			$this->pid_found = false;
		}
		else
		{
			// Insert a new record.
			$db->insert_query("threadprefixes", $insert_array);
			$pid = $db->insert_id();
		}
		
		$this->increment_tracker('threadprefixes');
		
		$output->print_progress("end");
		
		return $pid;
	}
	
	function convert_data($data)
	{
		$insert_data = array();
		
		// Check existing user profile field record.
		$this->pid_found = $this->check_existing_record($data['name']);
		
		if(empty($this->dz_forums_threadclasses))
		{
			$this->dz_forums_threadclasses = $this->dz_cache_forums_threadclasses();
		}
		
		if(empty($this->all_forums))
		{
			$this->all_forums = $this->cache_mybb_threadprefixes_forums();
		}
		
		if(empty($this->all_groups))
		{
			$this->all_groups = $this->cache_mybb_threadprefixes_groups();
		}
		
		// Discuz! values.
		$forums = array();
		$groups = -1;
		$typeids = explode(",", $data['typeids']);
		$fids = explode(",", $data['fids']);
		$mods = explode(",", $data['mods']);
		for($i = 0; $i < intval($data['count']); $i++)
		{
			if(array_key_exists($fids[$i], $this->dz_forums_threadclasses) && array_key_exists(intval($typeids[$i]), $this->dz_forums_threadclasses[$fids[$i]]['enabled_prefixes']))
			{
				$forums[] = intval($fids[$i]);
			}
			if($mods[$i] != '0')
			{
				$groups = -2;
			}
		}
		
		if($this->pid_found !== false)
		{
			$insert_data['pid'] = $this->pid_found['pid'];
			$insert_data['prefix'] = $this->pid_found['prefix'];
			$insert_data['displaystyle'] = $this->pid_found['displaystyle'];
			if(array_search($insert_data['pid'], $this->all_forums) === false)
			{
				$insert_data['forums'] = $this->pid_found['forums'];
				if(defined("DZX25_CONVERTER_THREADCLASS_DEPS") && DZX25_CONVERTER_THREADCLASS_DEPS)
				{
					foreach($forums as $forum)
					{
						$insert_data['forums'] .= ',' . $this->get_import->fid($forum);
					}
				}
			}
			if(defined("DZX25_CONVERTER_THREADCLASS_DEPS") && DZX25_CONVERTER_THREADCLASS_DEPS && $groups == -2)
			{
				if(array_search($insert_data['pid'], $this->all_groups) !== false)
				{
					$insert_data['groups'] = implode(",", $this->mod_usergroups);
				}
				else
				{
					$pid_found_groups = $this->mod_usergroups;
					foreach(explode(",", $this->pid_found['groups']) as $group)
					{
						$pid_found_groups[] = $group;
					}
					$pid_found_groups = array_unique($pid_found_groups);
					$insert_data['groups'] = implode(",", $pid_found_groups);
				}
			}
			else
			{
				$insert_data['groups'] = $this->pid_found['groups'];
			}
			
			$insert_data['import_pids'] = implode(",", array_filter(array_unique(array_merge(explode(",", $this->pid_found['import_pids']), explode(",", $data['typeids'])))));
			
			$this->pid_found = true;
		}
		else
		{
			$insert_data['prefix'] = encode_to_utf8($data['name'], $this->settings['encode_table'], "threadprefixes");
			$insert_data['displaystyle'] = $insert_data['prefix'];
			
			if(defined("DZX25_CONVERTER_THREADCLASS_DEPS") && DZX25_CONVERTER_THREADCLASS_DEPS)
			{
				$insert_data['forums'] = '';
				foreach($forums as $forum)
				{
					$insert_data['forums'] .= $this->get_import->fid($forum) . ',';
				}
				$insert_data['forums'] = trim($insert_data['forums'], ",");
				
				if($groups == -2)
				{
					$insert_data['groups'] = implode(",", $this->mod_usergroups);
				}
			}
			
			$insert_data['import_pids'] = $data['typeids'];
		}
		
		return $insert_data;
	}
	
	function fetch_total()
	{
		global $import_session;
		
		// Get number of distinct threadclasses in Discuz!
		if(!isset($import_session['total_threadprefixes']))
		{
			$query = $this->old_db->query("
				SELECT 
					name,
					GROUP_CONCAT(fid) AS fids,
					GROUP_CONCAT(moderators) AS mods 
				FROM ".OLD_TABLE_PREFIX."forum_threadclass 
				GROUP BY name
					");
			$import_session['total_threadprefixes'] = $this->old_db->num_rows($query);
		}
		
		return $import_session['total_threadprefixes'];
	}
	
	function pre_setup()
	{
		global $db;
		$query = $db->simple_select("threadprefixes", "pid,forums,groups");
		while($prefix = $db->fetch_array($query))
		{
			if($prefix['forums'] == '-1')
			{
				$this->all_forums[] = $prefix['pid'];
			}
			if($prefix['groups'] == '-1')
			{
				$this->all_groups[] = $prefix['pid'];
			}
		}
		$db->free_result($query);
	}
	
	function cleanup()
	{
		global $db;
		
		if(empty($this->dz_forums_threadclasses))
		{
			$this->dz_forums_threadclasses = $this->dz_cache_forums_threadclasses();
		}
		
		// Update imported forums if it requires threadprefix.
		foreach($this->dz_forums_threadclasses as $threadclass)
		{
			if($threadclass['require_prefix'] == 1)
			{
				$fid = $this->get_import->fid($threadclass['fid']);
				$db->update_query("forums", array('requireprefix' => 1), "fid = {$fid}");
			}
		}
	}
	
	/**
	 * Check if $name threadprefix is already in our MyBB.
	 *
	 * @param int $name The name of a threadprefix
	 * @return array|bool The settings of this threadprefix or false if the record is not found in table `threadprefixes`
	 */
	function check_existing_record($name)
	{
		global $db;

		$encoded_name = encode_to_utf8($name, $this->settings['encode_table'], "threadprefixes");
		
		$where = "prefix='".$db->escape_string($name)."' OR prefix='".$db->escape_string($encoded_name)."'";
		$query = $db->simple_select("threadprefixes", "*", $where, array('limit' => 1));
		$duplicate = $db->fetch_array($query);
		$db->free_result($query);
		
		// Using strtolower and my_strtolower to check, instead of in the query, is exponentially faster
		// If we used LOWER() function in the query the index wouldn't be used by MySQL
		if(strtolower($duplicate['prefix']) == strtolower($name) || converter_my_strtolower($duplicate['prefix']) == converter_my_strtolower($encoded_name))
		{
			return $duplicate;
		}
		
		return false;
	}
	
	function cache_mybb_threadprefixes_forums()
	{
		$mybb_threadprefixes_forums = array();
		
		global $db;
		$query = $db->simple_select("threadprefixes", "pid,forums");
		while($prefix = $db->fetch_array($query))
		{
			if($prefix['forums'] == '-1')
			{
				$mybb_threadprefixes_forums[] = $prefix['pid'];
			}
		}
		$db->free_result($query);
		
		return $mybb_threadprefixes_forums;
	}
	
	function cache_mybb_threadprefixes_groups()
	{
		$mybb_threadprefixes_groups = array();
		
		global $db;
		$query = $db->simple_select("threadprefixes", "pid,groups");
		while($prefix = $db->fetch_array($query))
		{
			if($prefix['groups'] == '-1')
			{
				$mybb_threadprefixes_groups[] = $prefix['pid'];
			}
		}
		$db->free_result($query);
		
		return $mybb_threadprefixes_groups;
	}
	
	function dz_cache_forums_threadclasses()
	{
		$dz_forums_threadclasses = array();
		
		$query = $this->old_db->query("
				SELECT
					name,
					GROUP_CONCAT(fid) AS fids,
					GROUP_CONCAT(moderators) AS mods
				FROM ".OLD_TABLE_PREFIX."forum_threadclass
				GROUP BY name
					");
		while($threadclass = $this->old_db->fetch_array($query))
		{
			foreach(explode(",", $threadclass['fids']) as $fid)
			{
				//$fid = intval($fid);
				$dz_forums_threadclasses[$fid] = array(
						'fid' => $fid,
						'require_prefix' => 0,
						'enabled_prefixes' => array(),
				);
			}
		}
		$this->old_db->free_result($query);
		
		foreach($dz_forums_threadclasses as $threadclass)
		{
			$fid = $threadclass['fid'];
			$query = $this->old_db->simple_select("forum_forumfield", "threadtypes", "fid = {$fid}", array('limit' => 1));
			$result = $this->old_db->fetch_field($query, "threadtypes");
			$result = $this->board->dz_unserialize($result);
			if($result['required'])
			{
				$dz_forums_threadclasses[$fid]['require_prefix'] = 1;
			}
			$dz_forums_threadclasses[$fid]['enabled_prefixes'] = $result['types'];
			$this->old_db->free_result($query);
		}
		
		return $dz_forums_threadclasses;
	}
}


