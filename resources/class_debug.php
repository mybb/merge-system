<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class Debug {

	public $log;

	/**
	 * Class constructor
	 */
	function __construct()
	{
		$this->log = new Log();
	}
}

class Log {

	const ERROR = 1;
	const WARNING = 2;
	const EVENT = 3;
	const TRACE0 = 4;
	const TRACE1 = 5;
	const TRACE2 = 6;
	const TRACE3 = 7;
	const DATATRACE = 8;

	private $table_exists = false;
	private $caused_error = false;

	public function error($message)
	{
		$this->write(self::ERROR, $message);
	}

	public function warning($message)
	{
		$this->write(self::WARNING, $message);
	}

	public function event($message)
	{
		$this->write(self::EVENT, $message);
	}

	public function trace0($message)
	{
		$this->write(self::TRACE0, $message);
	}

	public function trace1($message)
	{
		$this->write(self::TRACE1, $message);
	}

	public function trace2($message)
	{
		$this->write(self::TRACE2, $message);
	}

	public function trace3($message)
	{
		$this->write(self::TRACE3, $message);
	}

	public function datatrace($message, $data)
	{
		$this->write(self::DATATRACE, $message.': '.var_export($data, true));
	}

	private function write($type, $message)
	{
		global $db;

		if(WRITE_LOGS != 1)
		{
			return;
		}

		$log_insert = array(
			'type' => intval($type),
			'message' => $this->generate_plain_backtrace(2).$message,
			'timestamp' => TIME_NOW,
		);
		$this->log_inserts[] = $log_insert;

		// If our database connection is established
		if(is_object($db) && $db->read_link)
		{
			// Create our debug table if it does not exist already
			$this->create_debug_table();

			// Loop through our queue of logs and insert into debug table
			foreach($this->log_inserts as $log_insert)
			{
				$log_insert['message'] = $db->escape_string($log_insert['message']);
				// This looks ugly and it is, but otherwise we'll produce SQL errors...
				if(strlen($log_insert['message']) > 65535)
				{
					$log_insert['message'] = substr($log_insert['message'], 0, 65531)."...";
				}

				// If we caused an error and the message we're trying to insert isn't the SQL error we'll skip the message
				if($this->caused_error && substr($log_insert['message'], 0, 9) != "$type: 20")
				{
					continue;
				}

				$this->caused_error = true;
				$db->insert_query("debuglogs", $log_insert);
				$this->caused_error = false;
			}

			// Clear out our log queue now that they're all inserted
			$this->log_inserts = array();
		}
	}

	private function create_debug_table()
	{
		global $db;

		if($this->table_exists == true)
		{
			return;
		}

		if(!is_object($db) || !$db->read_link)
		{
			return;
		}

		if(!$db->table_exists("debuglogs"))
		{
			switch($db->type)
			{
				case "sqlite":
					$db->write_query("CREATE TABLE ".TABLE_PREFIX."debuglogs (
						dlid INTEGER PRIMARY KEY,
						type int(2) NOT NULL default '0',
						message text NOT NULL,
						timestamp bigint(30) NOT NULL default '0'
					);");
					break;
				case "pgsql":
					$db->write_query("CREATE TABLE ".TABLE_PREFIX."debuglogs (
						dlid serial,
						type int NOT NULL default '0',
						message text NOT NULL,
						timestamp bigint NOT NULL default '0',
						PRIMARY KEY(dlid)
					);");
					break;
				default:
					// The collation is hardcoded to avoid issues - probably there's a better fix?
					$db->write_query("CREATE TABLE ".TABLE_PREFIX."debuglogs (
						dlid int unsigned NOT NULL auto_increment,
						type int(2) NOT NULL default '0',
						message text NOT NULL,
						timestamp bigint(30) NOT NULL default '0',
						PRIMARY KEY(dlid)
					) ENGINE=MyISAM CHARACTER SET utf8 COLLATE utf8_general_ci;");
			}
		}

		$this->table_exists = true;
	}

	/**
	 * Generates a plain backtrace if the server supports it.
	 *
	 * @return string The generated backtrace
	 */
	function generate_plain_backtrace($shift=1)
	{
		if(!function_exists("debug_backtrace"))
		{
			return "";
		}

		$backtrace = "";

		$trace = debug_backtrace();

		// Strip off n number of function from trace
		for($i=0; $i < $shift; ++$i)
		{
			array_shift($trace);
		}

		foreach($trace as $call)
		{
			if(!$call['file']) $call['file'] = "[PHP]";
			if(!$call['line']) $call['line'] = "&nbsp;";
			if($call['class']) $call['function'] = $call['class'].$call['type'].$call['function'];
			$call['file'] = str_replace(substr(MYBB_ROOT, 0, -1), "", $call['file']);
			$backtrace .= "File: {$call['file']} Line: {$call['line']} Function: {$call['function']} -> \r\n";
		}

		return $backtrace;
	}

	public function __destruct()
	{
		global $start_timer, $load_timer, $db;

		$load_time = $start_timer-$load_timer;

		$end_timer = microtime(true);
		$total_time = $end_timer-$start_timer;

		$php_time = number_format($total_time - $db->query_time, 7);
		$query_time = number_format($db->query_time, 7);

		if($total_time > 0)
		{
			$percentphp = number_format((($php_time/$total_time) * 100), 2);
			$percentsql = number_format((($query_time/$total_time) * 100), 2);
		}
		else
		{
			// if we've got a super fast script...  all we can do is assume something
			$percentphp = 0;
			$percentsql = 0;
		}

		$phpversion = PHP_VERSION;

		$serverload = get_server_load();

		$current_memory_usage = get_memory_usage();
		if($current_memory_usage)
		{
			$memory_usage = " / Memory Usage: ".get_friendly_size($current_memory_usage);
		}
		else
		{
			$memory_usage = '';
		}

		$this->trace0("Generated in {$total_time} seconds ({$percentphp}% PHP / {$percentsql}% MySQL) / Initialize Load Time: {$load_time} / SQL Queries: {$db->query_count}{$memory_usage} PHP version: {$phpversion} / Server Load: {$serverload}");
	}
}

?>