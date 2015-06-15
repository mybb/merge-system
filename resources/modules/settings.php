<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 */

abstract class Converter_Module_Settings extends Converter_Module
{

	/**
	 * Update setting in the database
	 *
	 * @param string $name The name of the setting being inserted
	 * @param string $value The value of the setting being inserted
	 */
	public function update_setting($name, $value)
	{
		global $db, $output, $lang;

		$this->debug->log->trace0("Updating setting {$name}");

		$output->print_progress("start", $lang->sprintf($lang->module_settings_updating, htmlspecialchars_uni($name)));

		$modify = array(
			'value' => $db->escape_string($value)
		);

		$this->debug->log->datatrace('$value', $value);

		$db->update_query("settings", $modify, "name='{$name}'");

		$this->increment_tracker('settings');

		$output->print_progress("end");
	}

	/**
	 * Rebuild the settings file at the end of this
	 */
	function finish()
	{
		rebuild_settings();
	}

	// Nothing to do for settings, they're handled differently
	function convert_data($data) {}
	function insert($data) {}
}


