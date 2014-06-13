<?php
/**
 * MyBB 1.6
 * Copyright 2009 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 */

class Converter_Module_Events extends Converter_Module
{
	public $default_values = array(
		'import_eid' => 0,
		'cid' => 0,
		'uid' => 0,
		'name' => '',
		'description' => '',
		'visible' => 0,
		'private' => 0,
		'dateline' => 0,
		'starttime' => 0,
		'endtime' => 0,
		'timezone' => '',
		'ignoretimezone' => 0,
		'usingtime' => 0,
		'repeats' => ''
	);

	/**
	 * Insert an event into database
	 *
	 * @param event The insert array going into the MyBB database
	 */
	public function insert($data)
	{
		global $db, $output;

		$output->print_progress("start", $data[$this->settings['progress_column']]);

		$this->debug->log->datatrace('$data', $data);

		// Call our currently module's process function
		$data = $this->convert_data($data);

		// Should loop through and fill in any values that aren't set based on the MyBB db schema or other standard default values
		$data = $this->process_default_values($data);

		foreach($data as $key => $value)
		{
			$insert_array[$key] = $db->escape_string($value);
		}

		$this->debug->log->datatrace('$insert_array', $insert_array);

		$db->insert_query("events", $insert_array);
		$eid = $db->insert_id();

		$this->increment_tracker('events');

		$output->print_progress("end");

		return $eid;
	}
}

?>