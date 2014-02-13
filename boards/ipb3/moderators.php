<?php
/**
 * MyBB 1.6
 * Copyright � 2009 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
  * License: http://www.mybb.com/about/license
 *
 * $Id: moderators.php 4394 2010-12-14 14:38:21Z ralgith $
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class IPB3_Converter_Module_Moderators extends Converter_Module_Moderators {

	var $settings = array(
		'friendly_name' => 'moderators',
		'progress_column' => 'moderator_id',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session, $db;
		
		$query = $this->old_db->query("
			SELECT mr.*, IF(mr.is_group = 1, m.id, mr.member_id) as member_id 
			FROM ".OLD_TABLE_PREFIX."moderators mr
			LEFT JOIN ".OLD_TABLE_PREFIX."members m ON(mr.group_id = m.mgroup) 
			LIMIT {$this->trackers['start_moderators']}, {$import_session['moderators_per_screen']}
		");
		while($moderator = $this->old_db->fetch_array($query))
		{
			// Can't be empty or a group
			if($moderator['member_id'] == -1 || $moderator['member_id'] == "")
			{
				continue;
			}
			
			$check_query = $db->simple_select("moderators", "COUNT(mid) as count", "id='".$this->get_import->uid($moderator['member_id'])."' AND fid='".$this->get_import->fid($moderator['forum_id'])."' AND isgroup='0'"); 
			if($db->fetch_field($check_query, 'count'))
			{
				$db->free_result($check_query);
				continue;
			}
			
			$this->insert($moderator);			
		}
	}
	
	function convert_data($data)
	{
		$insert_data = array();
				
		// Invision Power Board 3 values
		$insert_data['fid'] = $this->get_import->fid($data['forum_id']);
		$insert_data['id'] = $this->get_import->uid($data['member_id']);
		$insert_data['caneditposts'] = $data['edit_post'];
		$insert_data['candeleteposts'] = $data['delete_post'];
		$insert_data['canviewips'] = $data['view_ip'];
		$insert_data['canopenclosethreads'] = $data['close_topic'];
		$insert_data['canmovetononmodforum'] = $data['move_topic'];
		
		return $insert_data;
	}
	
	function fetch_total()
	{
		global $import_session;
		
		// Get number of moderators
		if(!isset($import_session['total_moderators']))
		{
			$query = $this->old_db->simple_select("moderators", "COUNT(*) as count");
			$import_session['total_moderators'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}
		
		return $import_session['total_moderators'];
	}
}

?>