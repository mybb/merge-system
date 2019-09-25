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

class XENFORO2_Converter_Module_Forums extends Converter_Module_Forums
{
	var $settings = array(
		'friendly_name' => 'forums',
		'progress_column' => 'node_id',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session, $db;

		// Get forums..
		$query = $this->old_db->query("SELECT n.*, 
					f.allow_posting, f.count_messages, 
					lf.link_url
				FROM ".OLD_TABLE_PREFIX."node n 
				LEFT JOIN ".OLD_TABLE_PREFIX."forum f ON(f.node_id=n.node_id) 
				LEFT JOIN ".OLD_TABLE_PREFIX."link_forum lf ON(lf.node_id=n.node_id) 
				WHERE node_type_id in ('Category','Forum','LinkForum') 
				ORDER BY n.node_id ASC 
				LIMIT {$this->trackers['start_forums']}, {$import_session['forums_per_screen']}");
		while($forum = $this->old_db->fetch_array($query))
		{
			$fid = $this->insert($forum);

			// Update parent list.
			if($forum['parent_node_id'] == '0')
			{
				$db->update_query("forums", array('parentlist' => $fid), "fid = '{$fid}'");
			}
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// Xenforo 2 values
		$insert_data['import_fid'] = $data['node_id'];
		$insert_data['name'] = encode_to_utf8($this->fix_ampersand($data['title']), "node", "forums");
		$insert_data['description'] = encode_to_utf8($this->fix_ampersand($data['description']), "node", "forums");		
		$insert_data['disporder'] = $data['display_order'];

		// Category, forums, link forums.
		switch($data['node_type_id'])
		{
			case "Category":
				$insert_data['type'] = 'c';
				break;
			case "Forum":
				$insert_data['open'] = $data['allow_posting'];
				$insert_data['usepostcounts'] = $data['count_messages'];
			case "LinkForum":
				if(!empty($data['link_url']))
				{
					$insert_data['linkto'] = $data['link_url'];
				}
			default:
				$insert_data['type'] = 'f';
				$insert_data['import_pid'] = $data['parent_node_id'];
				break;
		}

		// xf's active forums can be accessed via URLs but MyBB's can't.
		$insert_data['active'] = $data['display_in_list'];

		return $insert_data;
	}
	
	function fetch_total()
	{
		global $import_session;

		// Get number of forums including only categories, forums, link forums.
		if(!isset($import_session['total_forums']))
		{
			$query = $this->old_db->simple_select("node", "COUNT(*) as count", "node_type_id in ('Category','Forum','LinkForum')");
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

		parent::cleanup();
	}
}

