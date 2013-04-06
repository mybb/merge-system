<?php
/**
 * MyBB 1.6
 * Copyright 2009 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 * $Id: usertitles.php 4395 2010-12-14 14:43:03Z ralgith $
 */

class Converter_Module_Usertitles extends Converter_Module
{
		
	public $default_values = array(
		'posts' => 0,
		'title' => '',
		'stars' => 1,
		'starimage' => 'star.gif'
	);
	
	/**
	 * Insert user titles into database
	 *
	 * @param usertitle The insert array going into the MyBB database
	 */
	public function insert($data)
	{
		global $db, $output;
		
		$this->debug->log->datatrace('$data', $data);
		
		$output->print_progress("start", $data[$this->settings['progress_column']]);
		
		// Call our currently module's process function
		$data = $this->convert_data($data);
		
		// Should loop through and fill in any values that aren't set based on the MyBB db schema or other standard default values
		$data = $this->process_default_values($data);
		
		foreach($data as $key => $value)
		{
			$insert_array[$key] = $db->escape_string($value);
		}
		
		$this->debug->log->datatrace('$insert_array', $insert_array);
		
		$db->insert_query("usertitles", $insert_array);
		$tid = $db->insert_id();
		
		$this->increment_tracker('usertitles');
		
		$output->print_progress("end");
		
		return $tid;
	}
}

?>