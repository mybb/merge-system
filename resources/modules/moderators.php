<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 *
 * $Id: moderators.php 4395 2010-12-14 14:43:03Z ralgith $
 */

class Converter_Module_Moderators extends Converter_Module
{
	public $default_values = array(
		'fid' => 0,
		'id' => 0,
		'isgroup' => 0,
		'caneditposts' => 1,
		'candeleteposts' => 1,
		'canviewips' => 1,
		'canopenclosethreads' => 1,
		'canmovetononmodforum' => 1,
		'canmanagethreads' => 1,
	);

	/**
	 * Insert moderator into database
	 *
	 * @param mod The insert array going into the MyBB database
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

		$db->insert_query("moderators", $insert_array);
		$mid = $db->insert_id();

		$this->increment_tracker('moderators');

		$output->print_progress("end");

		return $mid;
	}
}

?>