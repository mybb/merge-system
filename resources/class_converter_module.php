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
	 * @var DB_MySQL|DB_MySQLi|DB_PgSQL|DB_SQLite|PostgresPdoDbDriver|MysqlPdoDbDriver
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
	 * @param string $table In which table the array will be inserted. Used to determine the length
	 *
	 * @return array
	 */
	public function prepare_insert_array($values, $table='')
	{
		global $import_session, $db, $lang;

		$column_info = array();
		if(!empty($table))
		{
			$column_info = get_column_length_info($table);
		}

		$data = array_merge($this->default_values, $values);
		$insert_array = array();

		foreach($data as $key => $value)
		{
			$value_original = $value;
			if(!empty($column_info[$key]['type']))
			{
				$column = $column_info[$key];
			}
			else
			{
				$column = array();
				$this->board->log_column_error_notice_in_progress($table, $key, $lang->sprintf($lang->warning_prepare_data_unknown_column, $import_session['module'], TABLE_PREFIX.$table, $key));
			}

			// It's expected to be a binary field data in MyBB.
			// Actual column type varies, could be varbinary, *blob, bytea, etc.
			if(isset($this->binary_fields) && in_array($key, $this->binary_fields))
			{
				$value_length = strlen($value);

				// It'll be stored into a binary column.
				if(isset($column['type']) && $column['type'] == MERGE_DATATYPE_BIN)
				{
					if(isset($column['length_table']))
					{
						$limit = $column['length_table'];
						// Issue a warning (or an error?) of a data truncation of a binary field.
					}
					else
					{
						$limit = $column['length'];
					}

					if($value_length > $limit)
					{
						$value = substr($value, 0, $limit);
						$this->board->set_error_notice_in_progress($lang->sprintf($lang->warning_prepare_data_data_truncation_binary, TABLE_PREFIX.$table, $key, $column['def_type'], $value_length, $limit));
						$this->debug->log->warning($lang->sprintf($lang->warning_prepare_data_data_truncated, TABLE_PREFIX.$table, $key, $column['def_type'], 'BINARY', var_export(bin2hex($value_original), true), var_export(bin2hex($value), true)));
					}
				}
				else
				{
					$this->board->log_column_error_notice_in_progress($table, $key, $lang->sprintf($lang->warning_prepare_data_mismatched_column, $import_session['module'], TABLE_PREFIX.$table, $key, $column['def_type'], 'BINARY'));
				}

				$insert_array[$key] = $db->escape_binary($value);
			}
			// It's expected to be an integer field data in MyBB.
			// Actual column type varies, could be *int, numeric/decimal, etc.
			else if(isset($this->integer_fields) && in_array($key, $this->integer_fields))
			{
				$value = strtolower(trim((string) $value));
				if(strpos($value, '.') !== false)
				{
					$value = substr($value, 0, strpos($value, '.'));
				}

				if(!is_numeric($value))
				{
					$value = (string) ((int) $value);
					$this->board->set_error_notice_in_progress($lang->sprintf($lang->warning_prepare_data_data_casting_integer, TABLE_PREFIX.$table, $key, $column['def_type']));
					$this->debug->log->warning($lang->sprintf($lang->warning_prepare_data_data_casted, TABLE_PREFIX.$table, $key, $column['def_type'], 'The original', 'INTEGER', var_export($value_original, true), var_export($value, true)));
				}
				else if(strpos($value, 'e') !== false)
				{
					$value = number_format($value, 0, '.', '');
				}

				// Remove the sign, '+' or '-', if any, and save the positiveness.
				$int_is_positive = true;
				$value_length = strlen($value);
				if($value_length)
				{
					switch($value[0])
					{
						case '-':
							$int_is_positive = false;
						case '+':
							// Some older PHP versions may return false.
							$value = substr($value, 1);
							if($value ===  false)
							{
								$value = '';
							}
							break;
					}
				}
				// Remove any leftmost '0' and recover one '0' if necessary.
				$value = ltrim($value, '0');
				if(empty($value))
				{
					$value = '0';
				}

				$value_length = strlen($value);

				// If the target column is of boolean type.
				if(isset($column['type']) && ($column['type'] == MERGE_DATATYPE_BOOL || isset($column['type_table']) && $column['type_table'] == MERGE_DATATYPE_BOOL))
				{
					// PHP 5.4 and lower fix.
					$value = (int) $value;
					if(empty($value))
					{
						$value = 0;
					}
					else
					{
						$value = 1;
					}
				}
				// If the target column is of integer or fixed-point type.
				else if(isset($column['type']) && ($column['type'] == MERGE_DATATYPE_INT || $column['type'] == MERGE_DATATYPE_FIXED))
				{
					$int_is_limitless = false;
					$int_limit = '0';
					if($column['type'] == MERGE_DATATYPE_FIXED)
					{
						if(isset($column['length_table'], $column['scale_table']))
						{
							$fixed_point_precision = $column['length_table'];
							$fixed_point_scale = $column['scale_table'];
						}
						else
						{
							$fixed_point_precision = $column['length'];
							$fixed_point_scale = $column['scale'];
						}

						if($fixed_point_precision == -1)
						{
							$int_is_limitless = true;
						}
						else
						{
							$int_limit = str_repeat('9', $fixed_point_precision - $fixed_point_scale);
						}

					}
					else if(isset($column['min_table'], $column['max_table']))
					{
						$int_limit = $int_is_positive ? $column['max_table'] :  $column['min_table'];
					}
					else
					{
						$int_limit = $int_is_positive ? $column['max'] :  $column['min'];
					}

					if($int_limit[0] == '-')
					{
						$int_limit = substr($int_limit, 1);
					}

					$int_is_truncated = false;
					if(!$int_is_limitless)
					{
						$int_limit_length = strlen($int_limit);
						if($value_length > $int_limit_length)
						{
							$value = $int_limit;
							$int_is_truncated = true;
						}
						else if($value_length == $int_limit_length)
						{
							for($i = 0; $i < $value_length; $i++)
							{
								if($value[$i] != $int_limit[$i])
								{
									if((int) $value[$i] > (int) $int_limit[$i])
									{
										$value = $int_limit;
										$int_is_truncated = true;
									}
									break;
								}
							}
						}
					}

					if(!$int_is_positive && !empty($value))
					{
						$value = '-' . $value;
					}

					if($int_is_truncated)
					{
						$this->board->set_error_notice_in_progress($lang->sprintf($lang->warning_prepare_data_data_truncation_integer, TABLE_PREFIX.$table, $key, $column['def_type'], $value_original, $int_limit));
						$this->debug->log->warning($lang->sprintf($lang->warning_prepare_data_data_truncated, TABLE_PREFIX.$table, $key, $column['def_type'], 'INTEGER', var_export($value_original, true), var_export($value, true)));
					}
				}
				else
				{
					$this->board->log_column_error_notice_in_progress($table, $key, $lang->sprintf($lang->warning_prepare_data_mismatched_column, $import_session['module'], TABLE_PREFIX.$table, $key, $column['def_type'], 'INTEGER'));
				}

				$insert_array[$key] = $value;
			}
			// It's expected to be a string/character field data in MyBB.
			// Actual column type varies, could be *char*, *text, etc.
			else
			{
				$value_char_length = mb_strlen($value);
				$value_byte_length = strlen($value);

				// It'll be stored into a binary column.
				if(isset($column['type']) && $column['type'] == MERGE_DATATYPE_CHAR)
				{
					if(isset($column['length_table'], $column['length_type_table']))
					{
						$limit = $column['length_table'];
						$limit_type = $column['length_type_table'];
					}
					else
					{
						$limit = $column['length'];
						$limit_type = $column['length_type'];
					}

					if($limit_type == MERGE_DATATYPE_CHAR_LENGTHTYPE_CHAR && $value_char_length > $limit)
					{
						$value = my_substr($value, 0, $limit);
						$this->board->set_error_notice_in_progress($lang->sprintf($lang->warning_prepare_data_data_truncation_string, TABLE_PREFIX.$table, $key, $column['def_type'], 'char', $value_char_length, $limit));
						$this->debug->log->warning($lang->sprintf($lang->warning_prepare_data_data_truncated, TABLE_PREFIX.$table, $key, $column['def_type'], 'STRING (by char)', var_export($value_original, true), var_export($value, true)));
					}
					else if($limit_type == MERGE_DATATYPE_CHAR_LENGTHTYPE_BYTE && $value_byte_length > $limit)
					{
						$value = mb_strcut($value, 0, $limit);
						$this->board->set_error_notice_in_progress($lang->sprintf($lang->warning_prepare_data_data_truncation_string, TABLE_PREFIX.$table, $key, $column['def_type'], 'byte', $value_byte_length, $limit));
						$this->debug->log->warning($lang->sprintf($lang->warning_prepare_data_data_truncated, TABLE_PREFIX.$table, $key, $column['def_type'], 'STRING (by byte)', var_export($value_original, true), var_export($value, true)));
					}
				}
				else
				{
					$this->board->log_column_error_notice_in_progress($table, $key, $lang->sprintf($lang->warning_prepare_data_mismatched_column, $import_session['module'], TABLE_PREFIX.$table, $key, $column['def_type'], 'STRING'));
				}

				$insert_array[$key] = $db->escape_string($value);
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

			// This board doesn't have that specific module so skip it
			if(!file_exists(MERGE_ROOT."boards/{$import_session['board']}/{$module_name}.php")) {
				continue;
			}

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


