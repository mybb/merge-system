<?php
/**
 * MyBB 1.6
 * Copyright © 2009 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
  * License: http://www.mybb.com/about/license
 *
 * $Id$
 * Modified for Mingle Forums 1.0 by http://www.communityplugins.com
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class MINGLE_Converter_Module_Usertitles extends Converter_Module_Usertitles {

	var $settings = array(
		'friendly_name' => 'usertitles',
		//'progress_column' => 'option_value',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session, $db;

		//defaults			
		$title_names = array('level_newb_name'=>'Newbie', 'level_one_name'=>'Beginner', 'level_two_name'=>'Advanced', 'level_three_name'=>'Pro');
		$title_values = array('level_newb'=>0, 'level_one'=>25, 'level_two'=>50, 'level_three'=>100);
		
		$stars = 0;
		
		//get options and unserialize
		$query = $this->old_db->simple_select("options", "option_value", "option_name='mingleforum_options'");
		$mingleforum_options = $this->old_db->fetch_array($query);
		$mf_options = unserialize($mingleforum_options['option_value']);
				
		//merge defaults with options
		$mf_options = array_merge($title_names, $title_values, $mf_options);
		foreach($mf_options as $key => $title)
		{
			//if its a key we want
			if(array_key_exists($key, $title_names))
			{
				$usertitle = array();

				$usertitle['title'] = $mf_options[$key];
				
				$value_key = str_replace('_name', '', $key);
				$usertitle['posts'] = $mf_options[$value_key];

				$usertitle['stars'] = $stars;
				
				$tid = $this->insert($usertitle);
				
				$stars++;
			}
		}
	}
	
	function convert_data($data)
	{
		// Mingle values (all custom already)
		
		return $data;
	}
	
	function fetch_total()
	{
		global $import_session;
		
		// Mingle has fixed groups
		$import_session['total_usertitles'] = 4;
		
		return $import_session['total_usertitles'];
	}
	
	//drop existing usertitles
	function pre_setup()
	{
		global $db;
		
		$db->delete_query("usertitles");
		$db->query("ALTER TABLE `".TABLE_PREFIX."usertitles` AUTO_INCREMENT=1");
	}
}

?>
