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

class Converter_Module
{
	public $board = null;

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
		if($converter_class->parser_class == "plain" || ($converter_class->parser_class != "html" && !file_exists(MERGE_ROOT."boards/".$import_session['board']."/bbcode_parser.php")))
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
			require_once MERGE_ROOT."boards/".$import_session['board']."/bbcode_parser.php";
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
	 */
	public function prepare_insert_array($values)
	{
		global $db;

		$data = array_merge($this->default_values, $values);
		
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
		}

		return $insert_array;
	}

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
}

?>