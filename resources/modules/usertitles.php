<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 */

class Converter_Module_Usertitles extends Converter_Module
{

	public $default_values = array(
		'posts' => 0,
		'title' => '',
		'stars' => 1,
		'starimage' => 'star.gif'
	);

	public $integer_fields = array(
		'posts',
		'stars',
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

		// Should loop through and fill in any values that aren't set based on the MyBB db schema or other standard default values and escape them properly
		$insert_array = $this->prepare_insert_array($data);

		$this->debug->log->datatrace('$insert_array', $insert_array);

		$db->insert_query("usertitles", $insert_array);
		$tid = $db->insert_id();

		$this->increment_tracker('usertitles');

		$output->print_progress("end");

		return $tid;
	}
}

?>