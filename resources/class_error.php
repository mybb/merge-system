<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 */

class debugErrorHandler extends errorHandler {

	/**
	 * Initializes the error handler
	 *
	 */
	function __construct()
	{
		global $debug;

		$this->debug = &$debug;

		parent::__construct();
	}

	/**
	 * Parses a error for processing.
	 *
	 * @param string $type The error type (i.e. E_ERROR, E_FATAL)
	 * @param string $message The error message
	 * @param string $file The error file
	 * @param integer $line The error line
	 * @return boolean True if parsing was a success, otherwise assume a error
	 */
	function error($type, $message, $file=null, $line=0, $allow_output=true)
	{
		// Error reporting turned off (either globally or by @ before erroring statement)
		if(error_reporting() == 0)
		{
			return true;
		}

		if(in_array($type, $this->ignore_types))
		{
			return true;
		}

		$file = str_replace(MYBB_ROOT, "", $file);

		// Do we have a PHP error?
		if(my_strpos(my_strtolower($this->error_types[$type]), 'warning') === false)
		{
			$this->debug->log->error("\$type: {$type} \$message: {$message} \$file: {$file} \$line: {$line}");
		}
		// PHP Warning
		else
		{
			$this->debug->log->warning("\$type: {$type} \$message: {$message} \$file: {$file} \$line: {$line}");
		}

		return parent::error($type, $message, $file, $line, $allow_output);
	}

	/**
	 * Triggers a user created error
	 * Example: $error_handler->trigger("Some Warning", E_USER_ERROR);
	 *
	 * @param string $message Message
	 * @param int $type Type
	 */
	function trigger($message="", $type=E_USER_ERROR)
	{
		$this->debug->log->error("\$message: {$message} \$type: {$type}");

		parent::trigger($message, $type);
	}
}

