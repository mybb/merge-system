<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 */

class Converter_Module_Settings extends Converter_Module
{

	/**
	 * Update setting in the database
	 *
	 * @param name The name of the setting being inserted
	 * @param value The value of the setting being inserted
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
}

?>