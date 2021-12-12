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

abstract class Converter
{
	/**
	 * An array of custom defined errors (i.e. attachments directory permission error)
	 */
	var $errors = array();

	/**
	 * @var Debug
	 */
	var $debug;

	/**
	 * @var DB_MySQL|DB_MySQLi|DB_PgSQL|DB_SQLite|PostgresPdoDbDriver|MysqlPdoDbDriver
	 */
	var $old_db;

	/**
	 * @var array
	 */
	var $trackers = array();

	/**
	 * @var Cache_Handler
	 */
	var $get_import;

	/**
	 * @var array
	 */
	var $settings;

	/**
	 * An array of supported databases
	 * defaulting to every databases if not set by the board itself
	 */
	var $supported_databases = array("mysql", "pgsql", "sqlite");


	/**
	 * String of the bulletin board name
	 *
	 * @var string
	 */
	var $bbname;

	/**
	 * String of the plain bulletin board name
	 *
	 * @var string
	 */
	var $plain_bbname;

	/**
	 * Whether or not this module requires the loginconvert.php plugin
	 *
	 * @var boolean
	 */
	var $requires_loginconvert = false;

	/**
	 * Array of all of the modules
	 *
	 * @var array
	 */
	var $modules = array();

	/**
	 * The table we check to verify it's "our" database
	 *
	 * @var string
	 */
	var $check_table;

	/**
	 * The table prefix we suggest to use
	 *
	 * @var string
	 */
	var $prefix_suggestion = "";

	/**
	 * An array of board -> mybb groups
	 *
	 * @var array
	 */
	var $groups = array();

	/**
	 * What BBCode Parser we're using
	 *
	 * @var string
	 */
	var $parser_class;

	/**
	 * An array of columns that should be checked against the length of our database.
	 *
	 * Form: array('old_table' => array('our_table' => array('old_column' => 'new_column')));
	 *
	 * @var array
	 */
	var $column_length_to_check = array();

	/**
	 * Class constructor
	 */
	function __construct()
	{
		global $debug, $lang;
		// Set the module names here
		if(isset($this->modules))
		{
			foreach($this->modules as $key => &$module)
			{
				$key = str_replace(array("import_", ".", ".."), "", $key);
				$lang_string = "module_{$key}";
				if(isset($lang->$lang_string))
				{
					$module['name'] = $lang->$lang_string;
				}

				$desc_string = $lang_string."_desc";
				if(!empty($module['description']) && isset($lang->$desc_string))
				{
					$module['description'] = $lang->$desc_string;
				}
			}
		}

		$this->debug = &$debug;
		return 'MyBB';
	}

	/**
	 * Create a database connection on the old database we're importing from
	 *
	 */
	function db_connect()
	{
		global $import_session;

		$this->debug->log->trace0("Setting up connection to Convert DB.");

		// Attempt to connect to the db
		require_once MYBB_ROOT."inc/db_{$import_session['old_db_engine']}.php";

		switch($import_session['old_db_engine'])
		{
			case "sqlite":
				$this->old_db = new DB_SQLite;
				break;
			case "pgsql":
				$this->old_db = new DB_PgSQL;
				break;
			case "pgsql_pdo":
				$this->old_db = new PostgresPdoDbDriver();
				break;
			case "mysqli":
				$this->old_db = new DB_MySQLi;
				break;
			case "mysql_pdo":
				$this->old_db = new MysqlPdoDbDriver();
				break;
			default:
				$this->old_db = new DB_MySQL;
		}
		$this->old_db->type = $import_session['old_db_engine'];
		$this->old_db->connect(unserialize($import_session['connect_config']));
		$this->old_db->set_table_prefix($import_session['old_tbl_prefix']);

		define('OLD_TABLE_PREFIX', $import_session['old_tbl_prefix']);
	}

	function db_configuration()
	{
		global $mybb, $output, $import_session, $tableprefix, $lang;

		// Just posted back to this form?
		if($mybb->input['dbengine'])
		{
			$config_data = $mybb->input['config'][$mybb->input['dbengine']];

			if(strstr($mybb->input['dbengine'], "sqlite") !== false && (strstr($config_data['dbname'], "./") !== false || strstr($config_data['dbname'], "../") !== false))
			{
				$errors[] = $lang->error_database_relative;
			}
			else if(!file_exists(MYBB_ROOT."inc/db_{$mybb->input['dbengine']}.php"))
			{
				$errors[] = $lang->error_database_invalid_engine;
			}
			else
			{
				// Attempt to connect to the db
				require_once MYBB_ROOT."inc/db_{$mybb->input['dbengine']}.php";

				switch($mybb->input['dbengine'])
				{
					case "sqlite":
						$this->old_db = new DB_SQLite;
						break;
					case "pgsql":
						$this->old_db = new DB_PgSQL;
						break;
					case "pgsql_pdo":
						$this->old_db = new PostgresPdoDbDriver();
						break;
					case "mysqli":
						$this->old_db = new DB_MySQLi;
						break;
					case "mysql_pdo":
						$this->old_db = new MysqlPdoDbDriver();
						break;
					default:
						$this->old_db = new DB_MySQL;
				}
				$this->old_db->error_reporting = 0;

				$connect_config['type'] = $mybb->input['dbengine'];
				$connect_config['database'] = $config_data['dbname'];
				$connect_config['table_prefix'] = $config_data['tableprefix'];
				$connect_config['hostname'] = $config_data['dbhost'];
				$connect_config['username'] = $config_data['dbuser'];
				$connect_config['password'] = $config_data['dbpass'];
				$connect_config['encoding'] = $config_data['encoding'];

				$connection = $this->old_db->connect($connect_config);

				// -1 is returned if we can connect to the server but not to the database
				if(!$connection || $connection === -1)
				{
					$errors[] = $lang->sprintf($lang->error_database_cant_connect, $config_data['dbhost']);
				}

				if(empty($errors))
				{
					// Need to check if it is actually installed here
					$this->old_db->set_table_prefix($config_data['tableprefix']);

					if(isset($this->check_table) && !empty($this->check_table) && !$this->old_db->table_exists($this->check_table))
					{
						$errors[] = $lang->sprintf($lang->error_database_wrong_table, $this->plain_bbname, $config_data['dbname']);
					}
				}

				// No errors? Save import DB info and then return finished
				if(!isset($errors) || !is_array($errors))
				{
					$output->print_header("{$this->plain_bbname} {$lang->database_configuration}");

					echo "<br />\n{$lang->database_check_success}<br /><br />\n";
					flush();

					$import_session['old_db_engine'] = $mybb->input['dbengine'];
					$import_session['old_db_host'] = $config_data['dbhost'];
					$import_session['old_db_user'] = $config_data['dbuser'];
					$import_session['old_db_pass'] = $config_data['dbpass'];
					$import_session['old_db_name'] = $config_data['dbname'];
					$import_session['old_tbl_prefix'] = $config_data['tableprefix'];
					$import_session['connect_config'] = serialize($connect_config);
					$import_session['encode_to_utf8'] = intval($mybb->input['encode_to_utf8']);

					// Create temporary import data fields
					create_import_fields();

					sleep(2);

					$this->check_column_length();

					$import_session['flash_message'] = $lang->database_success;
					return true;
				}
			}
		}

		$output->print_header("{$this->plain_bbname} {$lang->database_configuration}");

		// Check for errors
		if(isset($errors) && is_array($errors))
		{
			$error_list = error_list($errors);
			echo "<div class=\"error\">
			      <h3>{$lang->error}</h3>
				  <p>{$lang->error_database_list}:</p>
				  {$error_list}
				  <p>{$lang->error_database_continue}</p>
				  </div>";

		}
		else
		{
			echo "<p>".$lang->sprintf($lang->database_details, $this->plain_bbname)."</p>";
			if($import_session['old_db_engine'])
			{
				$mybb->input['dbengine'] = $import_session['old_db_engine'];
			}
			else
			{
				$mybb->input['dbengine'] = $mybb->config['database']['type'];
			}

			if($import_session['old_db_host'])
			{
				$mybb->input['config'][$mybb->input['dbengine']]['dbhost'] = $import_session['old_db_host'];
			}
			else
			{
				$mybb->input['config'][$mybb->input['dbengine']]['dbhost'] = 'localhost';
			}

			if($import_session['old_tbl_prefix'])
			{
				$tableprefix = $import_session['old_tbl_prefix'];
			}
			else
			{
				$tableprefix = $this->prefix_suggestion;
			}
			// This looks probably odd, but we want that the table prefix is shown everywhere correctly
			foreach($this->supported_databases as $dbs)
			{
				$mybb->input['config'][$dbs]['tableprefix'] = $tableprefix;
			}
			// Handling mysqli seperatly as the array above doesn't make a difference between mysql and mysqli
			$mybb->input['config']["mysqli"]['tableprefix'] = $tableprefix;

			if($import_session['old_db_user'])
			{
				$mybb->input['config'][$mybb->input['dbengine']]['dbuser'] = $import_session['old_db_user'];
			}
			else
			{
				$mybb->input['config'][$mybb->input['dbengine']]['dbuser'] = '';
			}

			if($import_session['old_db_name'])
			{
				$mybb->input['config'][$mybb->input['dbengine']]['dbname'] = $import_session['old_db_name'];
			}
			else
			{
				$mybb->input['config'][$mybb->input['dbengine']]['dbname'] = '';
			}
		}

		$import_session['autorefresh'] = "";
		$mybb->input['autorefresh'] = "no";

		$extra = $this->db_extra();

		$output->print_database_details_table($this->plain_bbname, $extra);

		$output->print_footer();

		return false;
	}

	/**
	 * Checks an array of columns whether their value fits in our database (#123)
	 */
	function check_column_length() {
		global $output, $lang, $import_session;

		// We need the total number of columns to check to  show our progress bar
		$total_checks = 0;

		foreach($this->column_length_to_check as $old_table => $t1) {
			foreach ($t1 as $new_table => $columns) {
				foreach ($columns as $old_column => $new_column) {
					$total_checks++;
				}
			}
		}

		if($total_checks == 0) {
			return;
		}

		$this->db_connect();

		$output->construct_progress_bar();

		echo $lang->column_length_check;
		flush();

		$progress = $last_percent = 0;


		// Structure for array is: array('old_table' => array('new_table' => array(array('old_column' => 'new_column2'))))

		$invalid_columns = array();
		foreach($this->column_length_to_check as $old_table => $t1) {
			foreach($t1 as $new_table => $columns) {
				$columnLength = get_length_info($new_table, false);

				foreach($columns as $old_column => $new_column) {
					// First: get the length of the largest entry
					$query = "SELECT LENGTH({$old_column}) as length FROM ".OLD_TABLE_PREFIX."{$old_table} ORDER BY length DESC LIMIT 1";
					$length = $this->old_db->fetch_field($this->old_db->query($query), 'length');

					if($length > $columnLength[$new_column]) {
						$invalid_columns[$new_table][$new_column] = $length;
					}

					++$progress;
					// 200 is maximum
					$percent = round(($progress/$total_checks)*200, 1);
					if($percent != $last_percent)
					{
						$output->update_progress_bar($percent, $lang->sprintf($lang->column_length_checking, $old_column, $old_table));
					}
					$last_percent = $percent;
				}
			}
		}

		$output->update_progress_bar(200, $lang->please_wait);

		echo $lang->done;

		if(!empty($invalid_columns)) {
			// Show an error

			$error = "<b>{$lang->error_column_length_desc}</b><br />";
			foreach($invalid_columns as $new_table => $t1) {
				$error .= $lang->sprintf($lang->error_column_length_table, $new_table)."<br />";
				foreach($t1 as $new_column => $length) {
					$error .= $lang->sprintf($lang->error_column_length, $new_column, $length)."<br />";
				}
			}

			// We need to mark the db_configuration module as run to make sure the "next" button redirects to the module list

			// Check to see if our module is in the 'resume modules' array still and remove it if so.
			$key = array_search($import_session['module'], $import_session['resume_module']);
			if(isset($key))
			{
				unset($import_session['resume_module'][$key]);
			}

			// Add our module to the completed list and clear it from the current running module field.
			$import_session['completed'][] = $import_session['module'];
			$import_session['module'] = '';

			$output->print_error($error);
		}
		flush();
	}

	/**
	 * Checks if the current module is done importing or not
	 *
	 * @return bool
	 */
	function check_if_done()
	{
		global $import_session, $lang;

		$this->debug->log->trace2("Checking to see if we have more importing to go: {$import_session['module']}");

		$module_name = str_replace(array("import_", ".", ".."), "", $import_session['module']);

		$this->debug->log->datatrace("total_{$module_name}, start_{$module_name}", array($import_session['total_'.$module_name], $this->trackers['start_'.$module_name]));

		// If there are more work to do, continue, or else, move onto next module
		if($import_session['total_'.$module_name] - $this->trackers['start_'.$module_name] <= 0 || $import_session['total_'.$module_name] == 0)
		{
			$import_session['disabled'][] = 'import_'.$module_name;
			$import_session['flash_message'] = $lang->sprintf($lang->import_successfully, $this->settings['friendly_name']);
			return true;
		}

		return false;
	}

	/**
	 * Used for modules if there are handleable errors during the import process
	 *
	 * @param string $error_message
	 */
	function set_error_notice_in_progress($error_message)
	{
		global $output, $import_session;

		$this->debug->log->error($error_message);

		$import_session['error_logs'][$import_session['module']][] = $error_message;

		$output->set_error_notice_in_progress($error_message);
	}

	/**
	 * @param int $gid
	 *
	 * @return int
	 */
	public function get_gid($gid)
	{
		// A default group, return the correct MyBB group
		if(isset($this->groups) && isset($this->groups[$gid]))
		{
			return $this->groups[$gid];
		}
		// Custom group
		else
		{
			$gid = $this->get_import->gid($gid);
			// we found the correct group
			if($gid > 0)
			{
				return $gid;
			}
			// Something went wrong
			else
			{
				return MYBB_REGISTERED;
			}
		}
	}

	/**
	 * Convert a comma seperated list of original groups ids in one of mybb
	 *
	 * @param string $gids A comma seperated list of original group ids
	 * @param int|array $remove Either a single group id or an array of group ids which shouldn't be in the group array
	 * @return string group id(s)
	 */
	function get_group_id($gids, $remove=array())
	{
		if(empty($gids))
		{
			return '';
		}

		if(!is_array($gids))
		{
			// Fix strings like ",{id}," and then explode
			$gids = explode(',', trim($gids, ','));
		}
		$gids = array_map('trim', $gids);

		$groups = array();

		foreach($gids as $gid)
		{
			$groups[] = $this->get_gid($gid);
		}

		$groups = array_unique($groups);

		if(!empty($remove))
		{
			if(!is_array($remove))
			{
				$remove = array($remove);
			}

			$groups = array_diff($groups, $remove);
		}

		return implode(',', $groups);
	}

	/**
	 * @return string HTML to add to the db configuration page
	 */
	function db_extra()
	{
		return "";
	}
}

