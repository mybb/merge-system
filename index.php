<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 */

$load_timer = microtime(true);

header('Content-type: text/html; charset=utf-8');
@set_time_limit(0);
@ini_set('display_errors', true);
@ini_set('memory_limit', -1);

$merge_version = "1.8.1";
$version_code = 1801;

// Load core files
define("MYBB_ROOT", dirname(dirname(__FILE__)).'/');
define("MERGE_ROOT", dirname(__FILE__).'/');
define("IN_MYBB", 1);
define("WRITE_LOGS", 1);
define("TIME_NOW", time());
// The encoding detection can cause timeout errors and is automatically skipped for larger strings
// However this may also happen on smaller strings - set this to 1 if you have that problem
define("SKIP_ENCODING_DETECTION", 0);

if(function_exists('date_default_timezone_set') && !ini_get('date.timezone'))
{
	date_default_timezone_set('GMT');
}

require_once MERGE_ROOT.'resources/class_debug.php';
$debug = new Debug;

$debug->log->trace0("MyBB Merge System Started: \$version_code: {$version_code} \$merge_version: {$merge_version}");

require_once MYBB_ROOT."inc/config.php";
if(!isset($config['database']['type']))
{
	if($config['dbtype'])
	{
		die('MyBB needs to be upgraded before you can convert.');
	}
	else
	{
		die('MyBB needs to be installed before you can convert.');
	}
}

// If we have register globals on and we're coming from the db config page it seems to screw up the $config variable
if(@ini_get("register_globals") == 1)
{
	$config_copy = $config;
}

require_once MYBB_ROOT."inc/class_core.php";
$mybb = new MyBB;

if(@ini_get("register_globals") == 1)
{
	$config = $config_copy;
	unset($config_copy);
}

require_once MYBB_ROOT."inc/class_error.php";
require_once MERGE_ROOT."resources/class_error.php";
$error_handler = new debugErrorHandler();

// Include the files necessary for converting
require_once MYBB_ROOT."inc/class_timers.php";
$timer = new timer;

require_once MYBB_ROOT.'inc/class_datacache.php';
$cache = new datacache;

require_once MYBB_ROOT."inc/functions_rebuild.php";
require_once MYBB_ROOT."inc/functions.php";
require_once MYBB_ROOT."inc/settings.php";
$mybb->settings = $settings;

// Language

require_once MYBB_ROOT."inc/class_language.php";
require_once MERGE_ROOT."resources/class_language.php";
$lang = new debugMyLanguage();
$lang->set_path(MERGE_ROOT."language");
$lang->load("global");

if(substr($mybb->settings['uploadspath'], 0, 2) == "./" || substr($mybb->settings['uploadspath'], 0, 3) == "../")
{
	$mybb->settings['uploadspath'] = MYBB_ROOT.$mybb->settings['uploadspath'];
}
else
{
	$mybb->settings['uploadspath'] = $mybb->settings['uploadspath'];
}

require_once MYBB_ROOT."inc/class_xml.php";

// Include the converter resources
require_once MERGE_ROOT."resources/functions.php";
require_once MERGE_ROOT.'resources/output.php';
$output = new converterOutput;

require_once MERGE_ROOT.'resources/class_converter.php';

$mybb->config = $config;

require_once MYBB_ROOT."inc/db_".$config['database']['type'].".php";
switch($config['database']['type'])
{
	case "sqlite":
		$db = new DB_SQLite;
		break;
	case "pgsql":
		$db = new DB_PgSQL;
		break;
	case "mysqli":
		$db = new DB_MySQLi;
		break;
	default:
		$db = new DB_MySQL;
}

// Check if our DB engine is loaded
if(!extension_loaded($db->engine))
{
	// Throw our super awesome db loading error
	$mybb->trigger_generic_error("sql_load_error");
}

if(function_exists('mb_internal_encoding'))
{
	@mb_internal_encoding("UTF-8");
}

// Connect to the installed MyBB database
define("TABLE_PREFIX", $config['database']['table_prefix']);
$db->connect($config['database']);
$db->set_table_prefix(TABLE_PREFIX);
$db->type = $config['database']['type'];

// Start up our main timer so we can aggregate performance data
$start_timer = microtime(true);

// Get the import session cache if exists
$import_session = $cache->read("import_cache", 1);

// Setup our arrays if they don't exist yet
if(!$import_session['resume_module'])
{
	$import_session['resume_module'] = array();
}

if(!$import_session['disabled'])
{
	$import_session['disabled'] = array();
}

if(!$import_session['resume_module'])
{
	$import_session['resume_module'] = array();
}

if($mybb->version_code < 1700 || $mybb->version_code >= 2000)
{
	$output->print_error($lang->indexpage_require);
}

// Are we done? Generate the report!
if(isset($mybb->input['reportgen']) && !empty($import_session['board']))
{
	$debug->log->event("Generating report for completed merge");

	// Get the converter up.
	require_once MERGE_ROOT."boards/".$import_session['board'].".php";
	$class_name = strtoupper($import_session['board'])."_Converter";

	$board = new $class_name;

	// List of statistics we'll be using
	$import_stats = array(
		'total_usergroups' => 'User Groups',
		'total_users' => 'Users',
		'total_categories' => 'Categories',
		'total_forums' => 'Forums',
		'total_forumperms' => 'Forum Permissions',
		'total_moderators' => 'Moderators',
		'total_threads' => 'Threads',
		'total_posts' => 'Posts',
		'total_attachments' => 'Attachments',
		'total_polls' => 'Polls',
		'total_pollvotes' => 'Poll Votes',
		'total_privatemessages' => 'Private Messages',
		'total_events' => 'Events',
		'total_icons' => 'Icons',
		'total_smilies' => 'Smilies',
		'total_settings' => 'Settings',
		'total_attachtypes' => 'Attachment Types'
	);

	$begin_date = gmdate("r", $import_session['start_date']);
	$end_date = gmdate("r", $import_session['end_date']);

	$import_session['newdb_query_count'] = my_number_format($import_session['newdb_query_count']);
	$import_session['olddb_query_count'] = my_number_format($import_session['olddb_query_count']);
	$import_session['total_query_time_friendly'] = my_friendly_time($import_session['total_query_time']);

	if(empty($import_session['total_query_time_friendly']))
	{
		$import_session['total_query_time_friendly'] = "0 seconds";
	}

	$generation_time = gmdate("r");

	$year = gmdate("Y");

	$debug->log->trace2("Generating report in {$mybb->input['reportgen']} format");

	// Did we request it in plain txt format?
	if($mybb->input['reportgen'] == "txt")
	{
		$ext = "txt";
		$mime = "text/plain";

		// Generate the list of all the modules we ran (Threads, Posts, Users, etc)
		$module_list = "";
		foreach($board->modules as $key => $module)
		{
			if(in_array($key, $import_session['completed']))
			{
				$module_list .= htmlspecialchars_decode($module['name'])."\r\n";
			}
		}

		if(empty($module_list))
		{
			$module_list = "{$lang->none}\r\n";
		}

		$errors = "";
		if(!empty($import_session['error_logs']))
		{
			foreach($board->modules as $key => $module)
			{
				if(array_key_exists($key, $import_session['error_logs']))
				{
					$errors .= "{$module['name']}:\r\n";
					$errors .= "\t".implode("\r\n\t", $import_session['error_logs'][$key])."\r\n";
				}
			}
		}

		if(empty($errors))
		{
			$errors = "{$lang->none}\r\n";
		}

		// This may seem weird but it's not. We determine the longest length of the title,
		// so we can then pad it all neatly in the txt file.
		$max_len = 0;
		foreach($import_stats as $key => $title)
		{
			if(array_key_exists($key, $import_session) && strlen($title) > $max_len)
			{
				$max_len = strlen($title);
			}
		}

		// Generate the list of stats we have (Amount of threads imported, amount of posts imported, etc)
		foreach($import_stats as $key => $title)
		{
			if(array_key_exists($key, $import_session))
			{
				$title = "{$title}: ";

				// Determine the amount of spaces we need to line it all up nice and neatly.
				$title = str_pad($title, $max_len+2);
				$import_totals .= "{$title}".my_number_format($import_session[$key])."\r\n";
			}
		}

		$output = $lang->sprintf($lang->report_txt,
			$board->plain_bbname,
			$begin_date,
			$end_date,
			$import_session['newdb_query_count'],
			$import_session['olddb_query_count'],
			$import_session['total_query_time_friendly'],
			$module_list,
			$board->bbname,
			$import_totals,
			$errors,
			$generation_time
		);
	}

	// Ah, our users requests our pretty html format!
	if($mybb->input['reportgen'] == "html")
	{
		$ext = "html";
		$mime = "text/html";

		// Generate the list of all the modules we ran (Threads, Posts, Users, etc)
		foreach($board->modules as $key => $module)
		{
			if(in_array($key, $import_session['completed']))
			{
				$module_list .= "<li>{$module['name']}</li>\n";
			}
		}

		if(empty($module_list))
		{
			$module_list = "<li>{$lang->none}</li>\n";
		}

		// Generate the list of stats we have (Amount of threads imported, amount of posts imported, etc)
		foreach($import_stats as $key => $title)
		{
			if(array_key_exists($key, $import_session))
			{
				$import_totals .= "<dt>{$title}</dt>\n";
				$import_totals .= "<dd>".my_number_format($import_session[$key])."</dd>\n";
			}
		}

		if(empty($import_totals))
		{
			$import_totals = "<dt>{$lang->none}</dt>\n";
		}

		$errors = "";
		if(!empty($import_session['error_logs']))
		{
			foreach($board->modules as $key => $module)
			{
				if(array_key_exists($key, $import_session['error_logs']))
				{
					$errors .= "<li><strong>{$module['name']}:</strong>\n";
					$errors .= "<ul><li>".implode("</li>\n<li>", $import_session['error_logs'][$key])."</li></ul>\n";
					$errors .= "</li>";
				}
			}
		}

		if(empty($errors))
		{
			$errors = "<li>{$lang->none}</li>\n";
		}

		$output = $lang->sprintf($lang->report_html,
			$board->plain_bbname,
			$begin_date,
			$end_date,
			$import_session['newdb_query_count'],
			$import_session['olddb_query_count'],
			$import_session['total_query_time_friendly'],
			$module_list,
			$board->bbname,
			$import_totals,
			$errors,
			$generation_time,
			$year
		);

	}

	header("HTTP/1.1 200 OK");
	header("Status: 200 OK");
	header('Content-Type: '.$mime);
	header("Content-Disposition: attachment; filename=\"report_".time().".{$ext}\"");
	header("Content-Length: ".strlen($output));

	echo $output;

	$debug->log->event("Report generated");
	exit;
}


// The placement of this function is important and it should stay here. $import_session['finished_convert'] is set
// during $mybb->input['action'] == 'finish' which displays the last page and also displays the links to the Report Generations.
// The Report Generations are run right above this piece of code which we "exit;" before we reach this code so we don't clear out
// our statistics we've got saved. This will only run the next time someone visits the merge system script after we visit the
// 'finished' page and we're not downloading a report for the last merge.
if($import_session['finished_convert'] == '1')
{
	$debug->log->event("Running import session cleanup");

	// Delete import session cache
	$import_session = null;
	update_import_session();
}

// MyBB Group constants. Used for better readability
// We need them here to make 100% sure they're defined when creating our classes
define("MYBB_GUESTS",		1);
define("MYBB_REGISTERED",	2);
define("MYBB_SMODS",		3);
define("MYBB_ADMINS",		4);
define("MYBB_AWAITING",		5);
define("MYBB_MODS",			6);
define("MYBB_BANNED",		7);

if($mybb->input['board'])
{
	$debug->log->event("Setting up board merge classes: {$mybb->input['board']}");

	// Sanatize and check if it exists.
	$mybb->input['board'] = str_replace(".", "", $mybb->input['board']);

	$debug->log->trace1("Loading board module {$mybb->input['board']}");

	if(!file_exists(MERGE_ROOT."boards/".$mybb->input['board'].".php"))
	{
		$output->print_error($lang->error_invalid_board);
	}

	// Get the converter up.
	require_once MERGE_ROOT."boards/".$mybb->input['board'].".php";
	$class_name = strtoupper($mybb->input['board'])."_Converter";

	$board = new $class_name;

	if($board->requires_loginconvert == true)
	{
		$debug->log->trace1("loginconvert plugin required for this board module");

		if(!file_exists(MYBB_ROOT."inc/plugins/loginconvert.php") && file_exists(MERGE_ROOT."loginconvert.php"))
		{
			$debug->log->trace2("Attempting to move loginconvert.php to inc/plugins/loginconvert.php");
			$writable = @fopen(MYBB_ROOT.'inc/plugins/loginconvert.php', 'wb');
			if($writable)
			{
				@fwrite($writable, file_get_contents(MERGE_ROOT."loginconvert.php"));
				@fclose($writable);
				@my_chmod(MYBB_ROOT.'inc/plugins/loginconvert.php', '0555');
				$debug->log->trace2("Successfully moved loginconvert.php to inc/plugins/ automatically");
			}
		}

		if(!file_exists(MYBB_ROOT."inc/plugins/loginconvert.php"))
		{
			$debug->log->error("Unable to setup loginconvert.php. Cannot continue script execution");

			$output->print_header($lang->loginconvert_header);

			echo $lang->loginconvert_message;
			
			echo "			<input type=\"hidden\" name=\"board\" value=\"".htmlspecialchars_uni($mybb->input['board'])."\" />";

			$output->print_footer();
		}

		$plugins_cache = $cache->read("plugins", true);
		$active_plugins = $plugins_cache['active'];

		$active_plugins['loginconvert'] = "loginconvert";

		$plugins_cache['active'] = $active_plugins;
		$cache->update("plugins", $plugins_cache);

		$debug->log->trace1("Activated loginconvert plugin");
	}

	// Save it to the import session so we don't have to carry it around in the url/source.
	$import_session['board'] = $mybb->input['board'];
}

// Did we just start running a specific module (user import, thread import, post import, etc)
if($mybb->input['module'])
{
	$debug->log->event("Setting up board module specific classes: {$mybb->input['module']}");

	// Set our $resume_module variable to the last module we were working on (if there is one)
	// incase we come back to it at a later time.
	$resume_module = $import_session['module'];

	if(!array_search($import_session['module'], $import_session['resume_module']))
	{
		$import_session['resume_module'][] = $resume_module;
	}

	// Save our new module we're working on to the import session
	$import_session['module'] = $mybb->input['module'];
}

// Otherwise show them the agreement and ask them to agree to it to continue.
if(!$import_session['first_page'] && !$mybb->input['first_page'])
{
	$debug->log->event("Showing first agreement/welcome page");

	define("BACK_BUTTON", false);

	$output->print_header($lang->welcome);

	echo "<script type=\"text/javascript\">function button_undisable() { document.getElementById('main_submit_button').disabled = false; document.getElementById('main_submit_button').className = 'submit_button'; } window.onload = button_undisable; </script>";

	echo "<p>{$lang->welcomepage_description}<a href=\"http://docs.mybb.com/1.8/merge/\" target=\"_blank\">{$lang->welcomepage_mergesystem}</a></p>
		<input type=\"hidden\" name=\"first_page\" value=\"1\" />";

	echo '<input type="checkbox" name="allow_anonymous_info" value="1" id="allow_anonymous" checked="checked" /> <label for="allow_anonymous"> '.$lang->welcomepage_anonymousstat.'</label> (<a href="http://docs.mybb.com/1.8/merge/running#anonymous-statistics" style="color: #555;" target="_blank"><small>'.$lang->welcomepage_informations.'</small></a>)<br />';
	echo '<input type="checkbox" name="close_board" value="1" id="close_board" checked="checked" /> <label for="close_board"> '.$lang->welcomepage_closeboard.'</label>';

	$output->print_warning($lang->welcomepage_note, $lang->welcomepage_pleasenote);

	echo '<noscript>';
	$output->print_warning($lang->error_js_off);
	echo '</noscript>';

	$output->print_footer("", "", 1, false, $lang->next, "id=\"main_submit_button\" disabled=\"disabled\"", "submit_button_disabled");
}


// Did we just pass the requirements check?
if($mybb->input['requirements_check'] == 1 && $import_session['requirements_pass'] == 1 && $mybb->request_method == "post")
{
	$debug->log->event("Passed requirements check");

	// Save the check to the import session and move on.
	$import_session['requirements_check'] = 1;

	update_import_session();
}
// Otherwise show our requirements check to our user
else if(!$import_session['requirements_check'] || ($mybb->input['first_page'] == 1 && $mybb->request_method == "post") || !$import_session['requirements_pass'])
{
	$debug->log->event("Showing requirements check page");

	$import_session['allow_anonymous_info'] = intval($mybb->input['allow_anonymous_info']);
	$import_session['first_page'] = 1;

	// We should close the board - which shouldn't be necessary if they would do the merge locally...
	if((int)$mybb->input['close_board'] == 1)
	{
		$db->update_query("settings", array("value" => 1), "name='boardclosed'");
		rebuild_settings();
	}

	define("BACK_BUTTON", false);

	$errors = array();
	$checks = array();

	$output->print_header($lang->requirementspage_check);

	$checks['version_check_status'] = '<span class="pass">'.$lang->requirementspage_uptodate.'</span>';

	// Check for a new version of the Merge System!
	require_once MYBB_ROOT."inc/class_xml.php";
	$contents = merge_fetch_remote_file("http://www.mybb.com/merge_version_check.php");
	if($contents)
	{
		$parser = new XMLParser($contents);
		$tree = $parser->get_tree();

		$latest_code = $tree['mybb_merge']['version_code']['value'];
		$latest_version = "<strong>".$tree['mybb_merge']['latest_version']['value']."</strong> (".$latest_code.")";
		if($latest_code > $version_code)
		{
			$errors['version_check'] = "{$lang->requirementspage_outofdatedesc} <span style=\"color: #C00;\">".$latest_version."</span> (<a href=\"http://www.mybb.com/downloads/merge-system\" target=\"_blank\">{$lang->download}</a>)";
			$checks['version_check_status'] = '<span class="fail">'.$lang->requirementspage_outofdate.'</span>';
			$debug->log->warning("This version of the merge system is out-of-date");
		}
	}

	// Uh oh, problemos mi amigo?
	if(!$contents || !$latest_code)
	{
		$checks['version_check_status'] = '<span class="pass"><i>'.$lang->requirementspage_unabletocheck.'</i></span>';
		$debug->log->warning("Unable to check version status against mybb.com version server");
	}

	// Check upload directory is writable
	$attachmentswritable = @fopen(MYBB_ROOT.'uploads/test.write', 'w');
	if(!$attachmentswritable)
	{
		$errors['attachments_check'] = $lang->requirementspage_chmoduploads.' <a href="http://docs.mybb.com/1.8/install/#file-permissions" target="_blank">'.$lang->requirementspage_chmod.'</a>'.$lang->requirementspage_chmoduploads;
		$checks['attachments_check_status'] = '<span class="fail"><strong>'.$lang->requirementspage_notwritable.'</strong></span>';
		@fclose($attachmentswritable);
		$debug->log->trace0("Attachments directory not writable");
	}
	else
	{
		$checks['attachments_check_status'] = '<span class="pass">'.$lang->requirementspage_attwritable.'</span>';
		@fclose($attachmentswritable);
		@my_chmod(MYBB_ROOT.'uploads', '0777');
		@my_chmod(MYBB_ROOT.'uploads/test.write', '0777');
		@unlink(MYBB_ROOT.'uploads/test.write');
		$debug->log->trace0("Attachments directory writable");
	}

	if(!empty($errors))
	{
		$output->print_warning(error_list($errors), $lang->requirementspage_reqfailed);
	}

	echo '<p><div class="border_wrapper">
			<div class="title">'.$lang->requirementspage_check.'</div>
		<table class="general" cellspacing="0">
		<thead>
			<tr>
				<th colspan="2" class="first last">'.$lang->requirementspage_req.'</th>
			</tr>
		</thead>
		<tbody>
		<tr class="first">
			<td class="first">'.$lang->requirementspage_mergeversion.'</td>
			<td class="last alt_col">'.$checks['version_check_status'].'</td>
		</tr>
		<tr class="alt_row">
			<td class="first">'.$lang->requirementspage_attwritabledesc2.'</td>
			<td class="last alt_col">'.$checks['attachments_check_status'].'</td>
		</tr>
		</tbody>
		</table>
		</div>
		</p>
		<input type="hidden" name="requirements_check" value="1" />';

	if(!empty($errors))
	{
		$import_session['requirements_pass'] = 0;
		echo '<p><strong>'.$lang->requirementspage_checkagain.'</strong></p>';
		$output->print_footer("", "", 1, false, $lang->requirementspage_checkagain);
	}
	else
	{
		$import_session['requirements_pass'] = 1;
		echo '<p><strong>'.$lang->requirementspage_congrats.'</strong></p>';
		$output->print_footer("", "", 1, false);
	}
}

// If no board is selected then we show the main page where users can select a board
if(!$import_session['board'])
{
	$debug->log->event("Show the board listing page");
	$output->board_list();
}
// Show the completion page
elseif(isset($mybb->input['action']) && $mybb->input['action'] == 'completed')
{
	$debug->log->event("Show the merge competion page");

	$import_session['finished_convert'] = 1;
	$import_session['agreement'] = 0;
	$import_session['first_page'] = 0;

	$output->finish_conversion();
}
// Perhaps we have selected to stop converting or we are actually finished
elseif(isset($mybb->input['action']) && $mybb->input['action'] == 'finish')
{
	$debug->log->event("Show the merge cleanup page");

	define("BACK_BUTTON", false);

	$output->print_header($lang->cleanup_header);

	// Delete import fields and update our cache's
	$output->construct_progress_bar();

	echo "<br />\n {$lang->cleanup_notice} \n";
	flush();

	delete_import_fields();

	$cache->update_stats();
	$output->update_progress_bar(30);

	$cache->update_usergroups();
	$output->update_progress_bar(60);

	$cache->update_forums();
	$output->update_progress_bar(90);

	$cache->update_forumpermissions();
	$output->update_progress_bar(120);

	$cache->update_moderators();
	$output->update_progress_bar(150);

	$cache->update_usertitles();
	$output->update_progress_bar(180);

	// Update import session cache
	$import_session['end_date'] = time();

	// Get the converter up.
	require_once MERGE_ROOT."boards/".$import_session['board'].".php";
	$class_name = strtoupper($import_session['board'])."_Converter";

	$board = new $class_name;

	// Are we sending anonymous data from the conversion?
	if($import_session['allow_anonymous_info'] == 1)
	{
		$debug->log->trace0("Sending anonymous data from the conversion");

		// List of statistics we'll be using
		$import_stats = array(
			'total_usergroups' => 'User Groups',
			'total_users' => 'Users',
			'total_cats' => 'Categories',
			'total_forums' => 'Forums',
			'total_forumperms' => 'Forum Permissions',
			'total_mods' => 'Moderators',
			'total_threads' => 'Threads',
			'total_posts' => 'Posts',
			'total_attachments' => 'Attachments',
			'total_polls' => 'Polls',
			'total_pollvotes' => 'Poll Votes',
			'total_privatemessages' => 'Private Messages',
			'total_events' => 'Events',
			'total_settings' => 'Settings',
		);
	
		$post_data = array();

		// Prepare data
		$post_data['post'] = "1";
		$post_data['title'] = $mybb->settings['bbname'];

		foreach($board->modules as $key => $module)
		{
			if(in_array($key, $import_session['completed']))
			{
				$post_data[$key] = "1";
			}
			else
			{
				$post_data[$key] = "0";
			}
		}

		// Generate the list of stats we have (Amount of threads imported, amount of posts imported, etc)
		foreach($import_stats as $key => $title)
		{
			if(array_key_exists($key, $import_session))
			{
				$post_data[$key]  = $import_session[$key];
			}
		}

		$post_data['newdb_query_count'] = intval($import_session['newdb_query_count']);
		$post_data['olddb_query_count'] = intval($import_session['olddb_query_count']);
		$post_data['start_date'] = $import_session['start_date'];
		$post_data['end_date'] = $import_session['end_date'];
		$post_data['board'] = $import_session['board'];
		$post_data['return'] = "1";
		$post_data['rev'] = $version_code;

		// Try and send statistics
		merge_fetch_remote_file("http://community.mybb.com/merge_stats.php", $post_data);
	}

	$import_session['allow_anonymous_info'] = 0;

	update_import_session();

	$output->update_progress_bar(200);

	echo "done.<br />\n";
	flush();

	// We cannot do a header() redirect here because on some servers with gzip or zlib auto compressing content, it creates an  Internal Server Error.
	// Who knows why. Maybe it wants to send the content to the browser after it trys and redirects?
	echo "<br /><br />\n{$lang->please_wait} <meta http-equiv=\"refresh\" content=\"2; url=index.php?action=completed\">";
	exit;
}
elseif($import_session['counters_cleanup'])
{
	$debug->log->event("Show the counters cleanup page");

	define("BACK_BUTTON", false);

	// Get the converter up.
	require_once MERGE_ROOT."boards/".$import_session['board'].".php";
	$class_name = strtoupper($import_session['board'])."_Converter";

	$board = new $class_name;

	require_once MERGE_ROOT.'resources/class_converter_module.php';
	require_once MERGE_ROOT.'resources/modules/posts.php';
	$module = new Converter_Module_Posts($board);

	$module->counters_cleanup();

	update_import_session();

	// Now that all of that is taken care of, refresh the page to continue on to whatever needs to be done next.
	// We cannot do a header() redirect here because on some servers with gzip or zlib auto compressing content, it creates an  Internal Server Error.
	// Who knows why. Maybe it wants to send the content to the browser after it trys and redirects?
	echo "<meta http-equiv=\"refresh\" content=\"0; url=index.php\">";;
	exit;
}
// Otherwise that means we've selected a module to run or we're in one
elseif($import_session['module'] && $mybb->input['action'] != 'module_list')
{
	$debug->log->event("Running a specific module");

	// Get the converter up.
	require_once MERGE_ROOT."boards/".$import_session['board'].".php";
	$class_name = strtoupper($import_session['board'])."_Converter";

	$board = new $class_name;

	// Are we ready to configure out database details?
	if($import_session['module'] == "db_configuration")
	{
		$debug->log->trace0("Configuring our module");

		// Show the database details configuration
		$result = $board->db_configuration();
	}
	// We've selected a module (or we're in one) that is valid
	elseif($board->modules[$import_session['module']])
	{
		$debug->log->trace0("Setting up our module");

		$module_name = str_replace(array("import_", ".", ".."), "", $import_session['module']);

		require_once MERGE_ROOT.'resources/class_converter_module.php';
		require_once MERGE_ROOT.'resources/modules/'.$module_name.'.php';
		require_once MERGE_ROOT."boards/".$import_session['board']."/".$module_name.".php";

		$importer_class_name = strtoupper($import_session['board'])."_Converter_Module_".ucfirst($module_name);

		$module = new $importer_class_name($board);

		// Open our DB Connection
		$module->board->db_connect();

		// See how many we have to convert
		$module->fetch_total();

		// Check to see if perhaps we're finished already
		if($module->board->check_if_done())
		{
			// If we have anything to do "on finish"
			if(method_exists($module, "finish"))
			{
				$module->finish();
			}

			$result = "finished";
		}
		// Otherwise, run the module
		else
		{
			// Get number of posts per screen from the form if it was just submitted
			if(isset($mybb->input[$module_name.'_per_screen']))
			{
				$import_session[$module_name.'_per_screen'] = intval($mybb->input[$module_name.'_per_screen']);

				// This needs to be here so if we "Pause" (aka terminate script execution) our "per screen" amount will still be saved
				update_import_session();
			}

			// Do we need to do any setting up or checking before we start the actual import?
			if(method_exists($module, "pre_setup"))
			{
				$module->pre_setup();

				// Incase we updated any $import_session variables while we were setting up
				update_import_session();
			}

			// Have we set our "per screen" amount yet?
			if($import_session[$module_name.'_per_screen'] <= 0 || $module->is_errors)
			{
				// Print our header
				$output->print_header($module->board->modules[$import_session['module']]['name']);

				// Do we need to check a table type?
				if(!empty($module->settings['check_table_type']))
				{
					$module->check_table_type($module->settings['check_table_type']);
				}
				$output->print_per_screen_page($module->settings['default_per_screen']);
			}
			else
			{
				// Yes, we're actually running a module now
				define("IN_MODULE", 1);

				// Print our header
				$output->print_header($module->board->modules[$import_session['module']]['name']);

				// A bit of stats to show the progress of the current import
				$output->calculate_stats();

				// Run, baby, run
				$result = $module->import();
			}

			$output->print_footer();
		}
	}
	// Otherwise we're trying to use an invalid module or we're still at the beginning
	else
	{
		$debug->log->trace0("Invalid module or still at the beginning. Redirect back to last step.");

		$import_session['resume_module'][] = $resume_module;
		$import_session['module'] = '';

		update_import_session();
		header("Location: index.php");
		exit;
	}

	// If the module returns "finished" then it has finished everything it needs to do. We set the import session
	// to blank so we go back to the module list
	if($result == "finished")
	{
		$debug->log->trace1("Module finished. Run cleanup if needed.");

		// Once we finished running a module we check if there are any post-functions that need to be run
		// For instance, ususally we need to run a post-function on the forums to update the 'parentlist' properly
		if(method_exists($module, "cleanup"))
		{
			$debug->log->trace2("Running module cleanup.");
			$module->cleanup();
		}

		// Once we finish with posts we always recount and update lastpost info, etc.
		if($import_session['module'] == "import_posts")
		{
			$debug->log->trace2("Running import_posts counters cleanup.");
			$module->counters_cleanup();
		}

		// Check to see if our module is in the 'resume modules' array still and remove it if so.
		$key = array_search($import_session['module'], $import_session['resume_module']);
		if(isset($key))
		{
			unset($import_session['resume_module'][$key]);
		}

		// Add our module to the completed list and clear it from the current running module field.
		$import_session['completed'][] = $import_session['module'];
		$import_session['module'] = '';
		update_import_session();

		// Now that all of that is taken care of, refresh the page to continue on to whatever needs to be done next.
		if(!headers_sent())
		{
			header("Location: index.php");
		}
		else
		{
			echo "<meta http-equiv=\"refresh\" content=\"0; url=index.php\">";;
		}
		exit;
	}
}
// Otherwise we've selected a board but we're not in any module so we show the module selection list
else
{
	$debug->log->event("Show the module selection list page.");

	// Set the start date for the end report.
	if(!$import_session['start_date'])
	{
		$import_session['start_date'] = time();
	}

	// Get the converter up.
	require_once MERGE_ROOT."boards/".$import_session['board'].".php";
	$class_name = strtoupper($import_session['board'])."_Converter";

	$board = new $class_name;

	$output->module_list();
}
?>