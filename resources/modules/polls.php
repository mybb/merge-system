<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 */

class Converter_Module_Polls extends Converter_Module
{
	public $default_values = array(
		'import_pid' => 0,
		'tid' => 0,
		'question' => '',
		'dateline' => 0,
		'options' => '',
		'votes' => '',
		'numoptions' => 2,
		'numvotes' => 0,
		'timeout' => 0,
		'closed' => 0,
		'multiple' => 0,
		'public' => 0
	);

	/**
	 * Insert poll into database
	 *
	 * @param poll The insert array going into the MyBB database
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

		$db->insert_query("polls", $insert_array);
		$pollid = $db->insert_id();

		$this->increment_tracker('polls');

		$output->print_progress("end");

		return $pollid;
	}
}

?>