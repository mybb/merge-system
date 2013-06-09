<?php
/**
 * MyBB 1.6
 * Copyright © 2009 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
  * License: http://www.mybb.com/about/license
 *
 * $Id$
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class MINGLE_Converter_Module_Moderators extends Converter_Module_Moderators {

	var $settings = array(
		'friendly_name' => 'moderators',
		'progress_column' => 'user_id',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select("usermeta", "*", "meta_key='wpf_moderator' AND meta_value <> 'mod_global'", array('limit_start' => $this->trackers['start_mods'], 'limit' => $import_session['mods_per_screen']));
		while($moderator = $this->old_db->fetch_array($query))
		{
			$fids = unserialize($moderator['meta_value']);
			if(count($fids) > 0)
			{
				foreach($fids as $key=>$fid)
				{
					$mod['id'] = $moderator['user_id'];
					$mod['fid'] = $fid;
					$mod['user_id'] = $moderator['user_id']; //needed for progress
					$this->insert($mod);
				}
			}
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// Mingle values
		$insert_data['fid'] = $this->get_import->fid($data['fid']);
		$insert_data['id'] = $this->get_import->uid($data['id']);

		return $insert_data;
	}

	function fetch_total()
	{

		global $import_session;

		// Get number of moderators (single serialized as single record, iterate over results)
		if(!isset($import_session['total_moderators']))
		{
			$query = $this->old_db->simple_select("usermeta", "count(*) as totalnum", "meta_key='wpf_moderator' AND meta_value <> 'mod_global'");
			$import_session['total_moderators'] = $this->old_db->fetch_field($query, 'totalnum');
			$this->old_db->free_result($query);
		}

		return $import_session['total_moderators'];
	}
}

?>
