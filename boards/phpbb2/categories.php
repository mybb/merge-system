<?php
/**
 * MyBB 1.6
 * Copyright © 2009 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
  * License: http://www.mybb.com/about/license
 *
 * $Id: categories.php 4394 2010-12-14 14:38:21Z ralgith $
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class PHPBB2_Converter_Module_Categories extends Converter_Module_Categories {

	var $settings = array(
		'friendly_name' => 'categories',
		'progress_column' => 'cat_id',
		'default_per_screen' => 1000,
	);
	
	function import()
	{
		global $import_session, $db;
			
		$query = $this->old_db->simple_select("categories", "*", "", array('limit_start' => $this->trackers['start_categories'], 'limit' => $import_session['categories_per_screen']));
		while($category = $this->old_db->fetch_array($query))
		{
			$fid = $this->insert($category);
			
			// Update parent list.
			$db->update_query("forums", array('parentlist' => $fid), "fid = '{$fid}'");
		}
	}
	
	function convert_data($data)
	{
		$insert_data = array();
		
		// Values from phpBB
		$insert_data['import_fid'] = intval($data['cat_id']);
		$insert_data['name'] = encode_to_utf8($data['cat_title'], "categories", "forums");
		$insert_data['disporder'] = $data['cat_order'];
		$insert_data['type'] = "c";

		return $insert_data;
	}
	
	function fetch_total()
	{
		global $import_session;
		
		// Get number of categories
		if(!isset($import_session['total_categories']))
		{
			$query = $this->old_db->simple_select("categories", "COUNT(*) as count");
			$import_session['total_categories'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);				
		}
		
		return $import_session['total_categories'];
	}
}

?>