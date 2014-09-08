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

class Converter
{
	/**
	 * An array of custom defined errors (i.e. attachments directory permission error)
	 */
	var $errors = array();

	var $debug;

	/**
	 * Class constructor
	 */
	function __construct()
	{
		global $debug;

		$this->debug = &$debug;
		return 'MyBB';
	}

	/**
	 * Create a database connection on the old database we're importing from
	 *
	 */
	function db_connect()
	{
		global $import_session, $cache;

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
			case "mysqli":
				$this->old_db = new DB_MySQLi;
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
		global $mybb, $output, $import_session, $db, $dboptions, $dbengines, $dbhost, $dbuser, $dbname, $tableprefix;

		// Just posted back to this form?
		if($mybb->input['dbengine'])
		{
			$config_data = $mybb->input['config'][$mybb->input['dbengine']];

			if(strstr($mybb->input['dbengine'], "sqlite") !== false && (strstr($config_data['dbname'], "./") !== false || strstr($config_data['dbname'], "../") !== false))
			{
				$errors[] = "You may not use relative URLs for SQLite databases. Please use a file system path (ex: /home/user/database.db) for your SQLite database.";
			}
			else if(!file_exists(MYBB_ROOT."inc/db_{$mybb->input['dbengine']}.php"))
			{
				$errors[] = 'You have selected an invalid database engine. Please make your selection from the list below.';
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
					case "mysqli":
						$this->old_db = new DB_MySQLi;
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
				if(!$connection)
				{
					$errors[] = "Could not connect to the database server at '{$config_data['dbhost']}' with the supplied username and password. Are you sure the hostname and user details are correct?";
				}

				if(empty($errors))
				{
					// Need to check if it is actually installed here
					$this->old_db->set_table_prefix($config_data['tableprefix']);

					if(isset($this->check_table) && !empty($this->check_table) && !$this->old_db->table_exists($this->check_table))
					{
						$errors[] = "The {$this->plain_bbname} database could not be found in '{$config_data['dbname']}'.  Please ensure {$this->plain_bbname} exists at this database and with this table prefix.";
					}
				}

				// No errors? Save import DB info and then return finished
				if(!is_array($errors))
				{
					$output->print_header("{$this->plain_bbname} Database Configuration");

					echo "<br />\nChecking database details... <span style=\"color: green\">success.</span><br /><br />\n";
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

					$import_session['flash_message'] = "Successfully configured and connected to the database.";
					return "finished";
				}
			}
		}

		$output->print_header("{$this->plain_bbname} Database Configuration");

		// Check for errors
		if(is_array($errors))
		{
			$error_list = error_list($errors);
			echo "<div class=\"error\">
			      <h3>Error</h3>
				  <p>There seems to be one or more errors with the database configuration information that you supplied:</p>
				  {$error_list}
				  <p>Once the above are corrected, continue with the conversion.</p>
				  </div>";

		}
		else
		{
			echo "<p>Please enter the database details for your installation of {$this->plain_bbname} you want to merge from.</p>";
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
				$mybb->input['config'][$mybb->input['dbengine']]['tableprefix'] = $import_session['old_tbl_prefix'];
			}
			else
			{
				$mybb->input['config'][$mybb->input['dbengine']]['tableprefix'] = $this->prefix_suggestion;
			}

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

		$output->print_database_details_table($this->plain_bbname);

		$output->print_footer();
	}

	/**
	 * Checks if the current module is done importing or not
	 *
	 */
	function check_if_done()
	{
		global $import_session;

		$this->debug->log->trace2("Checking to see if we have more importing to go: {$import_session['module']}");

		$module_name = str_replace(array("import_", ".", ".."), "", $import_session['module']);

		$this->debug->log->datatrace("total_{$module_name}, start_{$module_name}", array($import_session['total_'.$module_name], $this->trackers['start_'.$module_name]));

		// If there are more work to do, continue, or else, move onto next module
		if($import_session['total_'.$module_name] - $this->trackers['start_'.$module_name] <= 0 || $import_session['total_'.$module_name] == 0)
		{
			$import_session['disabled'][] = 'import_'.$module_name;
			$import_session['flash_message'] = "Successfully imported {$this->settings['friendly_name']}.";
			return "finished";
		}
	}

	/**
	 * Used for modules if there are handleable errors during the import process
	 *
	 */
	function set_error_notice_in_progress($error_message)
	{
		global $output, $import_session;

		$this->debug->log->error($error_message);

		$import_session['error_logs'][$import_session['module']][] = $error_message;

		$output->set_error_notice_in_progress($error_message);
	}
}

?>