<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 */

abstract class Converter_Module_Pollvotes extends Converter_Module
{
	public $default_values = array(
		'pid' => 0,
		'uid' => 0,
		'voteoption' => 0,
		'dateline' => 0,
		'ipaddress' => '',
	);

	// The pollvotes table has ONLY integer fields - use the array above
	// As we can't call array_keys here we need the constructor
	public $integer_fields;
	public function __construct($converter_class) {
		parent::__construct($converter_class);
		$this->integer_fields = array_keys($this->default_values);
	}

	public $binary_fields = array(
		'ipaddress',
	);


	/**
	 * Insert poll vote into database
	 *
	 * @param array $data The insert array going into the MyBB database
	 * @return int The new id
	 */
	public function insert($data)
	{
		global $db, $output;

		$this->debug->log->datatrace('$data', $data);

		$output->print_progress("start", $data[$this->settings['progress_column']]);

		// Call our currently module's process function
		$data = $this->convert_data($data);

		// Should loop through and fill in any values that aren't set based on the MyBB db schema or other standard default values and escape them properly
		$insert_array = $this->prepare_insert_array($data, 'pollvotes');

		$this->debug->log->datatrace('$insert_array', $insert_array);

		$db->insert_query("pollvotes", $insert_array);
		$pollvoteid = $db->insert_id();

		$this->increment_tracker('pollvotes');

		$output->print_progress("end");

		return $pollvoteid;
	}
}


