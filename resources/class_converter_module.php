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

abstract class Converter_Module
{
	/**
	 * @var Converter
	 */
	public $board = null;

	/**
	 * @var array
	 */
	var $default_values = array();

	/**
	 * @var DB_MySQL|DB_MySQLi|DB_PgSQL|DB_SQLite
	 */
	var $old_db;

	/**
	 * @var array
	 */
	var $settings;

	/**
	 * @var Cache_Handler
	 */
	var $get_import;

	/**
	 * @var array
	 */
	var $trackers;

	/**
	 * @var Debug
	 */
	var $debug;

	/**
	 * @var array
	 */
	var $errors = array();

	/**
	 * @var bool
	 */
	var $is_errors = false;

	/**
	 * @var array
	 */
	var $mark_as_run_modules = array();

	/**
	 * @param Converter $converter_class
	 */
	public function __construct($converter_class)
	{
		global $import_session, $debug, $db, $lang;

		if(isset($this->settings['friendly_name']))
		{
			$key = str_replace(" ", "_", $this->settings['friendly_name']);
			$lang_string = "module_{$key}";
			if(isset($lang->$lang_string))
			{
				$this->settings['friendly_name'] = $lang->$lang_string;
			}
			$this->settings['orig_name'] = $key;
		}

		// Setup & Share our variables and classes
		require_once MERGE_ROOT.'resources/class_cache_handler.php';
		$this->get_import = new Cache_Handler();

		// Setup our bbcode parser
		if(!isset($converter_class->parser_class))
		{
			$converter_class->parser_class = "";
		}

		// Plain class is needed as parent class anyways
		require_once MERGE_ROOT."resources/bbcode_plain.php";
		// If we're using the plain class or we don't have a custom one -> set it up
		if($converter_class->parser_class == "plain" || ($converter_class->parser_class != "html" && !file_exists(MERGE_ROOT."boards/{$import_session['board']}/bbcode_parser.php")))
		{
			$this->bbcode_parser = new BBCode_Parser_Plain();
		}
		// Using the HTML class? No need for extra checks
		else if($converter_class->parser_class == "html")
		{
			require_once MERGE_ROOT."resources/bbcode_html.php";
			$this->bbcode_parser = new BBCode_Parser_HTML();
		}
		// The only other case is a custom parser. A check whether the class exists is in the first if
		else
		{
			// It's possible that the custom handler is based on the html handler so we need to include it too
			require_once MERGE_ROOT."resources/bbcode_html.php";
			require_once MERGE_ROOT."boards/{$import_session['board']}/bbcode_parser.php";
			$this->bbcode_parser = new BBCode_Parser();
		}

		// Setup our trackers
		$this->trackers = array();
		$query = $db->simple_select("trackers");
		while($tracker = $db->fetch_array($query))
		{
			$this->trackers['start_'.$tracker['type']] = $tracker['count'];
		}

		$this->board = &$converter_class;
		$this->old_db = &$this->board->old_db;
		$this->board->settings = &$this->settings;
		$this->board->get_import = &$this->get_import;
		$this->board->trackers = &$this->trackers;
		$this->debug = &$debug;
	}

	/**
	 * Fills an array of insert data with default MyBB values if they were not specified
	 *
	 * @param array $values
	 * @param bool|string $table In which table the array will be inserted. Used to determine the length
	 *
	 * @return array
	 */
	public function prepare_insert_array($values, $table=false)
	{
		global $db;

		$column_length = array();
		if($table !== false) {
			$column_length = get_length_info($table);
		}

		$data = array_merge($this->default_values, $values);
		$insert_array = array();

		foreach($data as $key => $value)
		{
			if(isset($this->binary_fields) && in_array($key, $this->binary_fields))
			{
				$insert_array[$key] = $db->escape_binary($value);
			}
			else if(isset($this->integer_fields) && in_array($key, $this->integer_fields))
			{
				$insert_array[$key] = (int)$value;
			}
			else
			{
				$insert_array[$key] = $db->escape_string($value);
			}

			if(isset($column_length[$key]) && mb_strlen($insert_array[$key]) > $column_length[$key] && (!isset($this->binary_fields) || !in_array($key, $this->binary_fields))) {
				if(is_int($insert_array[$key])) {
					// TODO: check whether int(10) really can save "9999999999" as maximum
					$insert_array[$key] = (int)str_repeat('9', $column_length[$key]);
				} else {
					$insert_array[$key] = my_substr($insert_array[$key], 0, $column_length[$key]-3)."...";
				}
			}
		}

		return $insert_array;
	}

	/**
	 * @param array|string $tables
	 */
	public function check_table_type($tables)
	{
		global $output, $lang;

		if(!is_array($tables))
		{
			$tables = array($tables);
		}

		if($this->old_db->type == "mysqli" || $this->old_db->type == "mysql")
		{
			foreach($tables as $table)
			{
				$table_sql = $this->old_db->show_create_table($table);
				if(stripos($table_sql, "ENGINE=InnoDB") !== false)
				{
					$output->print_warning($lang->sprintf($lang->warning_innodb, $table));
					$this->debug->log->warning("{$table} is in InnoDB format. This can cause major slow-downs");
				}
			}
		}
	}

	/**
	 * @param string $type
	 * @param int $amount
	 */
	function increment_tracker($type, $amount=1)
	{
		global $db;

		$this->trackers['start_'.$type] += $amount;

		$replacements = array(
			"count"		=> (int) $this->trackers['start_'.$type],
			"type"		=> $db->escape_string($type)
		);
		$db->replace_query("trackers", $replacements);
	}

	/**
	 * Called every time when the module is setup (before "fetch_total" or "import")
	 */
	function pre_setup() {}

	/**
	 * Fetch the number of rows to insert. Needs to be the same number as "insert" is called!
	 *
	 * @return int
	 */
	abstract function fetch_total();

	/**
	 * Grab the data from the original database and call "insert" on every entry to insert.
	 * Query should have the usual limit clause
	 *
	 * @return void
	 */
	abstract function import();

	/**
	 * Used to show progress, insert data and insert the array. Also updates internal caches. Calls "convert_data"
	 * Usually the same for all boards
	 *
	 * @param array $data
	 *
	 * @return int The ID of the new row
	 */
	abstract function insert($data);

	/**
	 * Convert an array of original entry to an array that can be inserted into our database
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	abstract function convert_data($data);

	/**
	 * Perform any work after the new row has been inserted. Called from "insert", but not on all modules!
	 * TODO: call it from all modules
	 *
	 * @param array $unconverted_values
	 * @param array $converted_values
	 * @param int $id The ID of the mybb row
	 */
	function after_insert($unconverted_values, $converted_values, $id) {}

	/**
	 * Called after all rows are inserted. So basically after "insert" has been called as many times as "fetch_total" returned
	 */
	function finish() {}

	/**
	 * Called after "finish" is called. Except that the same
	 */
	function cleanup() {}

	/**
	 * Mark any dependencies as run if we haven't imported anything
	 */
	function mark_dependencies_as_run()
	{
		global $import_session;
		foreach($this->mark_as_run_modules as $module) {
			$module_name = str_replace(array("import_", ".", ".."), "", $module);
			$import_session['completed'][] = 'import_'.$module_name;
			$import_session['disabled'][] = 'import_'.$module_name;

			require_once MERGE_ROOT."resources/modules/{$module_name}.php";
			require_once MERGE_ROOT."boards/{$import_session['board']}/{$module_name}.php";

			$importer_class_name = strtoupper($import_session['board'])."_Converter_Module_".ucfirst($module_name);

			/** @var Converter_Module $moduleClass */
			$moduleClass = new $importer_class_name($this->board);
			$moduleClass->mark_dependencies_as_run();
		}
	}

}


