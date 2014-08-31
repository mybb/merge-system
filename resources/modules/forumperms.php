<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 */

class Converter_Module_Forumperms extends Converter_Module
{
	public $default_values = array(
		'gid' => 0,
		'canview' => 1,
		'canonlyviewownthreads' => 0,
		'canviewthreads' => 1,
		'candlattachments' => 1,
		'canpostthreads' => 1,
		'canpostreplys' => 1,
		'canpostattachments' => 1,
		'canratethreads' => 1,
		'caneditposts' => 1,
		'candeleteposts' => 1,
		'candeletethreads' => 1,
		'caneditattachments' => 1,
		'canpostpolls' => 1,
		'canvotepolls' => 1,
		'cansearch' => 1,
	);
	
	// The forumpermissions table has ONLY integer fields - use the array above
	// As we can't call array_keys here we need the constructor
	public $integer_fields;
	public function __construct($converter_class) {
		parent::__construct($converter_class);
		$this->integer_fields = array_keys($this->default_values);
	}

	/**
	 * Insert forumpermissions into database
	 *
	 * @param perm The insert array going into the MyBB database
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

		$query = $db->insert_query("forumpermissions", $insert_array);
		$fpid = $db->insert_id();

		$this->increment_tracker('forumperms');

		$output->print_progress("end");

		return $fpid;
	}
}

?>