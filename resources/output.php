<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 *
 * $Id: output.php 4394 2010-12-14 14:38:21Z ralgith $
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

/**
 * Class to create output from the converter scripts
 */
class converterOutput
{
	/**
	 * This is set to 1 if the header has been called.
	 *
	 * @var int 1 or 0
	 */
	var $doneheader;

	/**
	 * This is set to 1 if a form has been opened.
	 *
	 * @var int 1 or 0
	 */
	var $opened_form;

	/**
	 * Script name
	 *
	 * @var string
	 */
	var $script = "index.php";

	/**
	 * Steps for conversion
	 *
	 * @var array
	 */
	var $steps = array();

	/**
	 * Title of the system
	 *
	 * @var string
	 */
	var $title = "MyBB Merge System";

	/**
	* Internal position counter for friendly progress bar
	*
	* @var integer
	*/
	var $_internal_counter = 0;

	/**
	* Internal string for friendly name but in English singular form
	*
	* @var integer
	*/
	var $_friendly_name_singular = "";

	/**
	* Internal denominator for friendly progress bar percentage completed algorithm
	*
	* @var integer
	*/
	var $_progress_denominator = 0;

	/**
	* Internal indicator to see if the progress bar was already constructed or not
	*
	* @var integer
	*/
	var $_progress_bar_constructed = 0;

	var $_last_left = 0;

	/**
	 * Method to print the converter header
	 *
	 * @param string Page title
	 * @param string Icon to be used
	 * @param int Open a form 1/0
	 * @param int Error???
	 */
	function print_header($title="Welcome", $image="welcome", $form=1, $error=0)
	{
		global $mybb, $merge_version, $import_session;

		$this->doneheader = 1;

		echo <<<END
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
	<title>$this->title &gt; $title</title>
	<link rel="stylesheet" href="stylesheet.css" type="text/css" />
	<script type="text/javascript" src="../jscripts/prototype.js"></script>
	<script type="text/javascript" src="../jscripts/general.js"></script>
</head>
<body>
END;

		echo <<<END
		<div id="container">
		<div id="logo">
			<h1><span class="invisible">MyBB</span></h1>
		</div>
		<div id="inner_container">
		<div id="header">{$this->title} - Version: {$merge_version}</div>
		<div id="content">
END;
		if($form)
		{
			echo "\n	<form method=\"post\" action=\"".$this->script."\">\n";
			$this->opened_form = 1;
		}

		// Only if we're in a module
		$module_name = str_replace(array("import_", ".", ".."), "", $import_session['module']);
		if(IN_MODULE == 1)
		{
			echo "\n		<input type=\"hidden\" name=\"action\" value=\"module_list\" />\n";
			echo "\n		<div id=\"pause_button\"><input type=\"submit\" class=\"submit_button\" value=\"&laquo; Pause\" /></div>\n";

			define("BACK_BUTTON", false);
		}

		if($title != "")
		{
			echo <<<END
			<div><h2 class="$image">$title</h2></div>\n
END;
		}
	}

	/**
	 * Echo the contents out
	 *
	 * @param string Contents to echo out
	 */
	function print_contents($contents)
	{
		echo $contents;
	}

	/**
	 * Print an error block, and the footer.
	 *
	 * @param string Error string
	 */
	function print_error($message)
	{
		if(!$this->doneheader)
		{
			$this->print_header('Error', "", 0, 1);
		}
		echo "			<div class=\"error\">\n				";
		echo "<h3>Error</h3>";
		$this->print_contents($message);
		echo "\n			</div>";

		$this->print_footer();
	}

	/**
	 * Print an warning block
	 *
	 * @param string Error string
	 */
	function print_warning($message, $title="Warning")
	{
		echo "			<div class=\"error\">\n				";
		echo "<h3>{$title}</h3>";
		$this->print_contents($message);
		echo "\n			</div>";
	}

	/**
	 * Print a list of possible boards to convert from, and the footer
	 *
	 */
	function board_list()
	{
		if(!$this->doneheader)
		{
			$this->print_header();
		}

		echo "<p>Thank you for choosing MyBB. This wizard will guide you through the process of converting from your existing community to MyBB.";

		echo "<div class=\"border_wrapper\">\n";
		echo "<div class=\"title\">Board Selection</div>\n";
		echo "<table class=\"general\" cellspacing=\"0\">\n";
		echo "<tr>\n";
		echo "<th colspan=\"2\" class=\"first last\">Please select the board you wish to convert from.</th>\n";
		echo "</tr>\n";

		$dh = opendir(MERGE_ROOT."boards");
		while(($file = readdir($dh)) !== false)
		{
			if($file != "." && $file != ".." && get_extension($file) == "php")
			{
				$bb_name = str_replace(".php", "", $file);
				$board_script = file_get_contents(MERGE_ROOT."boards/{$file}");
				// Match out board name
				preg_match("#var \\\$bbname \= \"(.*?)\"\;#i", $board_script, $version_info);
				if($version_info[1])
				{
					$board_array[$bb_name] = $version_info[1];
				}
			}
		}

		asort($board_array);

		$class = "first";

		foreach($board_array as $bb_name => $version_info)
		{
			echo "<tr class=\"{$class}\">\n";
			echo "<td><label for=\"$bb_name\">$version_info</label></td>\n";
			echo "<td><input type=\"radio\" name=\"board\" value=\"$bb_name\" id=\"$bb_name\" /></td>\n";
			echo "</tr>\n";

			if($class == "alt_row")
			{
				$class = "";
			}
			else
			{
				$class = "alt_row";
			}
		}

		closedir($dh);
		echo "</table>\n";
		echo "</div>\n";

		$this->print_footer();
	}

	/**
	 * Print a list of modules and their dependencies for user to choose from, and the footer
	 */
	function module_list()
	{
		global $board, $import_session;

		if(count($board->modules) == count($import_session['completed']))
		{
			header("Location: index.php?action=finish");
			exit;
		}

		$this->print_header("Module Selection", "", 0);

		if($import_session['flash_message'])
		{
			echo "<div class=\"flash_success\"><p><em>{$import_session['flash_message']}</em></p></div>\n";
			$import_session['flash_message'] = null;
		}

		echo "<div class=\"border_wrapper\">\n";
		echo "<div class=\"title\">Module Selection</div>\n";
		echo "<table class=\"general\" cellspacing=\"0\">\n";
		echo "<tr>\n";
		echo "<th colspan=\"2\" class=\"first last\">Please select a module to run.</th>\n";
		echo "</tr>\n";

		$class = "first";
		$i = 0;

		foreach($board->modules as $key => $module)
		{
			++$i;
			$dependency_list = array();
			$awaiting_dependencies = 0;

			// Fetch dependent modules
			$dependencies = explode(',', $module['dependencies']);
			$icon = '';
			if(count($dependencies) > 0)
			{
				foreach($dependencies as $dependency)
				{
					if($dependency == '')
					{
						break;
					}

					$prefix = "";
					if($dependency != 'db_configuration')
					{
						$prefix = "Import {$board->plain_bbname} ";
					}

					if(!in_array($dependency, $import_session['completed']))
					{
						// Cannot be run yet
						$awaiting_dependencies = 1;
						$dependency_list[] = $prefix.$board->modules[$dependency]['name'];
						$icon = ' awaiting';
					}
					else
					{
						// Dependency has been run
						$dependency_list[] = "<del>".$prefix.$board->modules[$dependency]['name']."</del>\n";
					}
				}
			}

			if(in_array($key, $import_session['completed']))
			{
				// Module has been completed.  Thus show.
				$icon = ' completed';
			}

			if(count($board->modules) == $i)
			{
				$class .= " last";
			}

			echo "<tr class=\"{$class}\">\n";
			echo "<td class=\"first\"><div class=\"module{$icon}\">".$module['name']."</div>\n";

			if($module['description'])
			{
				echo "<div class=\"module_description\">".$module['description']."</div>\n";
			}

			if(in_array($key, $import_session['completed']))
			{
				// Module has been completed.  Thus show.
				echo "<div class=\"pass module_description\">Completed</div>\n";
			}

			if(count($dependency_list) > 0)
			{
				echo "<div class=\"module_description\"><small>Dependencies: ".implode(', ', $dependency_list)."</small></div>\n";
			}

			echo "</td>\n";
			echo "<td class=\"last\" width=\"1\">\n";
			echo "<form method=\"post\" action=\"{$this->script}\">\n";

			if($import_session['module'] == $key || in_array($key, $import_session['resume_module']))
			{
				echo "<input type=\"submit\" class=\"submit_button\" value=\"Resume &raquo;\" />\n";
			}
			elseif($awaiting_dependencies || in_array($key, $import_session['disabled']) || in_array($key, $import_session['completed']) && $key != "db_configuration")
			{
				echo "<input type=\"submit\" class=\"submit_button submit_button_disabled\" value=\"Run &raquo;\" disabled=\"disabled\" />\n";
			}
			else
			{
				echo "<input type=\"submit\" class=\"submit_button\" value=\"Run &raquo;\" />\n";
			}

			echo "<input type=\"hidden\" name=\"module\" value=\"{$key}\" />\n";
			echo "</form>\n";
			echo "</td>\n";
			echo "</tr>\n";

			if($class == "alt_row")
			{
				$class = "";
			}
			else
			{
				$class = "alt_row";
			}
		}

		echo "</table>\n";
		echo "</div><br />\n";
		echo '<p>After you have run the modules you want, continue to the next step in the conversion process.  The cleanup step will remove any temporary data created during the conversion.</p>';
		echo "<form method=\"post\" action=\"{$this->script}\">\n";
		echo '<input type="hidden" name="action" value="finish" />';
		echo '<div style="text-align:right"><input type="submit" class="submit_button" value="Cleanup &raquo;" /></div></form>';

		$this->print_footer('', '', 1);
	}

	/**
	 * Print a list of fields to be written in by user for database details.
	 *
	 * @param string Name of the bulletin board software
	 * @param string Any extra text to include (optional)
	 */
	function print_database_details_table($name, $extra="")
	{
		global $board, $dbengines, $dbhost, $dbuser, $dbname, $tableprefix, $oldver, $mybb;

		if(function_exists('mysql_connect'))
		{
			$dboptions['mysql'] = array(
				'class' => 'DB_MySQL',
				'title' => 'MySQL',
				'short_title' => 'MySQL',
			);
		}

		if(function_exists('mysqli_connect'))
		{
			$dboptions['mysqli'] = array(
				'class' => 'DB_MySQLi',
				'title' => 'MySQL Improved',
				'short_title' => 'MySQLi',
			);
		}

		if(function_exists('pg_connect'))
		{
			$dboptions['pgsql'] = array(
				'class' => 'DB_PgSQL',
				'title' => 'PostgreSQL',
				'short_title' => 'PostgreSQL',
			);
		}

		if(class_exists('PDO'))
		{
			$supported_dbs = PDO::getAvailableDrivers();
			if(in_array('sqlite', $supported_dbs))
			{
				$dboptions['sqlite'] = array(
					'class' => 'DB_SQLite',
					'title' => 'SQLite 3',
					'short_title' => 'SQLite',
				);
			}
		}

		// Loop through database engines
		foreach($dboptions as $dbfile => $dbtype)
		{
			if($mybb->input['dbengine'] == $dbfile)
			{
				$dbengines .= "<option value=\"{$dbfile}\" selected=\"selected\">{$dbtype['title']}</option>";
			}
			else
			{
				$dbengines .= "<option value=\"{$dbfile}\">{$dbtype['title']}</option>";
			}
		}

		echo "<script type=\"text/javascript\">
		function updateDBSettings()
		{
			dbengine = \$('dbengine').options[\$('dbengine').selectedIndex].value;
			$$('.db_settings').each(function(element)
			{
				element.className = 'db_settings';
				if(dbengine+'_settings' == element.id)
				{
					Element.show(element);
				}
				else
				{
					Element.hide(element);
				}
			});
		}
		Event.observe(window, 'load', updateDBSettings);
		</script>";

		$versions = "";
		if(count($board->supported_versions) >= 3)
		{
			foreach($board->supported_versions as $key => $nice_ver)
			{
				if($key == "name")
				{
					continue;
				}

				if($oldver == $key)
				{
					$versions .= "<option value=\"{$key}\" selected=\"selected\">{$nice_ver}</option>\n";
				}
				else
				{
					$versions .= "<option value=\"{$key}\">{$nice_ver}</option>\n";
				}
			}

			$versions = "<tbody>
			<tr>
	<th colspan=\"2\" class=\"first last\">Version</th>
</tr>
<tr class=\"last\">
	<td class=\"first\"><label for=\"old_board_version\">Version of Invision Power Board your running:</label></td>
	<td class=\"last alt_col\"><select name=\"old_board_version\">
<optgroup label=\"{$board->supported_versions['name']}\">
	{$versions}
</optgroup>
</td>
</tr>
</tbody>";
		}

		foreach($dboptions as $dbfile => $dbtype)
		{
			require_once MYBB_ROOT."inc/db_{$dbfile}.php";
			if(!class_exists($dbtype['class']))
			{
				continue;
			}

			$db = new $dbtype['class'];
			$encodings = $db->fetch_db_charsets();
			$encoding_select = '';
			if(!$mybb->input['config'][$dbfile]['dbhost'])
			{
				$mybb->input['config'][$dbfile]['dbhost'] = "localhost";
			}
			if(!isset($mybb->input['config'][$dbfile]['tableprefix']))
			{
				$mybb->input['config'][$dbfile]['tableprefix'] = "mybb_";
			}
			if(!$mybb->input['config'][$dbfile]['encoding'])
			{
				$mybb->input['config'][$dbfile]['encoding'] = "utf8";
			}

			$class = '';
			if(!$first && !$mybb->input['dbengine'])
			{
				$mybb->input['dbengine'] = $dbfile;
				$first = true;
			}
			if($dbfile == $mybb->input['dbengine'])
			{
				$class = "_selected";
			}

			$db_info[$dbfile] = "
				<tbody id=\"{$dbfile}_settings\" class=\"db_settings db_type{$class}\">
					<tr>
						<th colspan=\"2\" class=\"first last\">{$dbtype['title']} Database Settings</th>
					</tr>";

			// SQLite gets some special settings
			if($dbfile == 'sqlite')
			{
				$db_info[$dbfile] .= "
					<tr class=\"alt_row\">
						<td class=\"first\"><label for=\"config_{$dbfile}_dbname\">Database Path:</label></td>
						<td class=\"last alt_col\"><input type=\"text\" class=\"text_input\" name=\"config[{$dbfile}][dbname]\" id=\"config_{$dbfile}_dbname\" value=\"".htmlspecialchars_uni($mybb->input['config'][$dbfile]['dbname'])."\" /></td>
					</tr>";
			}
			// Others get db host, username, password etc
			else
			{
				$db_info[$dbfile] .= "
					<tr class=\"alt_row\">
						<td class=\"first\"><label for=\"config_{$dbfile}_dbhost\">Database Server Hostname:</label></td>
						<td class=\"last alt_col\"><input type=\"text\" class=\"text_input\" name=\"config[{$dbfile}][dbhost]\" id=\"config_{$dbfile}_dbhost\" value=\"".htmlspecialchars_uni($mybb->input['config'][$dbfile]['dbhost'])."\" /></td>
					</tr>
					<tr>
						<td class=\"first\"><label for=\"config_{$dbfile}_dbuser\">Database Username:</label></td>
						<td class=\"last alt_col\"><input type=\"text\" class=\"text_input\" name=\"config[{$dbfile}][dbuser]\" id=\"config_{$dbfile}_dbuser\" value=\"".htmlspecialchars_uni($mybb->input['config'][$dbfile]['dbuser'])."\" /></td>
					</tr>
					<tr class=\"alt_row\">
						<td class=\"first\"><label for=\"config_{$dbfile}_dbpass\">Database Password:</label></td>
						<td class=\"last alt_col\"><input type=\"password\" class=\"text_input\" name=\"config[{$dbfile}][dbpass]\" id=\"config_{$dbfile}_dbpass\" value=\"".htmlspecialchars_uni($mybb->input['config'][$dbfile]['dbpass'])."\" /></td>
					</tr>
					<tr class=\"last\">
						<td class=\"first\"><label for=\"config_{$dbfile}_dbname\">Database Name:</label></td>
						<td class=\"last alt_col\"><input type=\"text\" class=\"text_input\" name=\"config[{$dbfile}][dbname]\" id=\"config_{$dbfile}_dbname\" value=\"".htmlspecialchars_uni($mybb->input['config'][$dbfile]['dbname'])."\" /></td>
					</tr>";
			}

			// Now we're up to table settings
			$db_info[$dbfile] .= "
				<tr>
					<th colspan=\"2\" class=\"first last\">{$dbtype['title']} Table Settings</th>
				</tr>
				<tr class=\"first\">
					<td class=\"first\"><label for=\"config_{$dbfile}_tableprefix\">Table Prefix:</label></td>
					<td class=\"last alt_col\"><input type=\"text\" class=\"text_input\" name=\"config[{$dbfile}][tableprefix]\" id=\"config_{$dbfile}_tableprefix\" value=\"".htmlspecialchars_uni($mybb->input['config'][$dbfile]['tableprefix'])."\" /></td>
				</tr>
				";

			// Encoding selection only if supported
			if(is_array($encodings))
			{
				$select_options = "";
				foreach($encodings as $encoding => $title)
				{
					if($mybb->input['config'][$dbfile]['encoding'] == $encoding)
					{
						$select_options .= "<option value=\"{$encoding}\" selected=\"selected\">{$title}</option>";
					}
					else
					{
						$select_options .= "<option value=\"{$encoding}\">{$title}</option>";
					}
				}
				$db_info[$dbfile] .= "
					<tr class=\"last\">
						<td class=\"first\"><label for=\"config_{$dbfile}_encoding\">Table Encoding:</label></td>
						<td class=\"last alt_col\"><select name=\"config[{$dbfile}][encoding]\" id=\"config_{$dbfile}_encoding\">{$select_options}</select></td>
					</tr>

					</tbody>";
			}
		}
		$dbconfig = implode("", $db_info);

		if($mybb->input['encode_to_utf8'] === 0)
		{
			$encoding_checked_no = "checked=\"checked\"";
			$encoding_checked_yes = "";
		}
		else
		{
			$encoding_checked_yes = "checked=\"checked\"";
			$encoding_checked_no = "";
		}

		$encoding_utf8 = "<tbody>
		<tr>
			<tr>
				<th colspan=\"2\" class=\"first last\">Encode to UTF-8</th>
			</tr>
			<tr class=\"last\">
				<td class=\"first\"><label for=\"encode_to_utf8\">Automatically convert messages to UTF8?:<br /><small>Turn this off if the conversion creates<br />weird characters in your forum's messages.</small></label></td>
				<td class=\"last alt_col\"><input type=\"radio\" name=\"encode_to_utf8\" value=\"1\" class=\"radio_input radio_yes\" {$encoding_checked_yes} />Yes</label> <input type=\"radio\" name=\"encode_to_utf8\" value=\"0\" class=\"radio_input radio_no\" {$encoding_checked_no} />No
			</td>
		</tr>
		</tbody>";

		echo <<<EOF
<div class="border_wrapper">
<div class="title">$name Database Configuration</div>
<table class="general" cellspacing="0">
<tr>
	<th colspan="2" class="first last">Database Settings</th>
</tr>
<tr class="first">
	<td class="first"><label for="dbengine">Database Engine:</label></td>
	<td class="last alt_col"><select name="dbengine" id="dbengine" onchange="updateDBSettings();">{$dbengines}</select></td>
</tr>

$dbconfig
$versions
$encoding_utf8
$extra
</table>
</div>
<p>Once you have checked these details are correct, click next to continue.</p>
EOF;
	}

	/**
	 * Print final page
	 */
	function finish_conversion()
	{
		global $config, $import_session;

		if(!$this->doneheader)
		{
			$this->print_header("Completion", '', 1);
		}

		if(!isset($config['admin_dir']))
		{
			$config['admin_dir'] = "admin";
		}

		echo '<p>The current conversion session has been finished.  You may now go to your copy of <a href="../">MyBB</a> or your <a href="../'.$config['admin_dir'].'/index.php">Admin Control Panel</a>.  It is recommended that you run the Rebuild and Recount tools in the Admin CP.</p>';
		echo '
<p>Please remove this directory if you are not planning on converting any other forums.</p>';

		echo '<br />
<p>The following will allow you to download a detailed report generated by the converter in several styles.
<div class="border_wrapper">
<div class="title">Report Generation</div>
<table class="general" cellspacing="0">
<tr>
<th colspan="2" class="first last">Please select the report style you wish to generate.</th>
</tr>
<tr>
<td><label for="txt"> Plain Text File
</label></td>
<td><input type="radio" name="reportgen" value="txt" id="txt" /></td>
</tr>

<tr>
<td><label for="html"> HTML (Browser Viewable) File
</label></td>
<td><input type="radio" name="reportgen" value="html" id="html" /></td>
</tr>

</table>
</div>

		<div id="next_button"><input type="submit" class="submit_button" value="Download &raquo;" /></div>

</form>';

		$import_session['finished'] = '1';

		$this->print_footer('', '', 1, true);
	}

	/**
	 * Print the footer of the page
	 *
	 * @param string The next 'action'
	 * @param string The name of the next action
	 * @param int Do session update? 1/0
	 */
	function print_footer($next_action="", $name="", $do_session=1, $override_form=false, $next="Next", $button_extra="", $extra_class="")
	{
		global $import_session, $conf_global_not_found, $mybb;

		if($this->opened_form && $override_form != true)
		{
			if($mybb->input['autorefresh'] == "yes" || $mybb->input['autorefresh'] == "no")
			{
				$import_session['autorefresh'] = $mybb->input['autorefresh'];
			}

			if(IN_MODULE == 1)
			{
				echo "\n	</form>\n";
				echo "\n	<form method=\"post\" action=\"".$this->script."\">\n";
			}

			if($import_session['autorefresh'] == "yes" && !$conf_global_not_found)
			{
				echo "\n		<meta http-equiv=\"Refresh\" content=\"2; url=".$this->script."\" />";
				echo "\n		<div id=\"next_button\"><input type=\"submit\" class=\"submit_button {$extra_class}\" value=\"Redirecting... &raquo;\" alt=\"Click to continue, if you do not wish to wait.\" {$button_extra} /></div>";
			}
			else
			{

				echo "\n		<div id=\"next_button\"><input type=\"submit\" class=\"submit_button {$extra_class}\" value=\"{$next} &raquo;\" {$button_extra} /></div>";

			}
			echo "\n	</form>\n";

			// Only if we're in a module
			if($import_session['module'] && (!defined('BACK_BUTTON') || BACK_BUTTON != false))
			{
				echo "\n	<form method=\"post\" action=\"".$this->script."\">\n";
				if($import_session['module'] == 'db_configuration')
				{
					echo "\n		<input type=\"hidden\" name=\"action\" value=\"module_list\" />\n";
					echo "\n		<div id=\"exit_config_button\"><input type=\"submit\" class=\"submit_button {$extra_class}\" value=\"&laquo; Exit Configuration\" {$button_extra} /></div><br style=\"clear: both;\" />\n";
				}
				else
				{
					echo "\n		<input type=\"hidden\" name=\"action\" value=\"module_list\" />\n";
					echo "\n		<div id=\"back_button\"><input type=\"submit\" class=\"submit_button\" value=\"&laquo; Back\" {$button_extra} /></div><br style=\"clear: both;\" />\n";
				}
				echo "\n	</form>\n";

			}
			else
			{
				echo "\n <br style=\"clear: both;\" />";
			}
		}
		else
		{
			$formend = "";
		}

		echo <<<END
		</div>
		<div id="footer">
END;

		$copyyear = date('Y');
		echo <<<END
			<div id="copyright">
				MyBB &copy; 2002-{$copyyear} MyBB Group
			</div>
		</div>
		</div>
		</div>
</body>
</html>
END;
		if($do_session == 1)
		{
			update_import_session();
		}
		exit;
	}

	function print_inline_errors()
	{
		$this->print_error_page(true);
	}

	function print_error_page($inline=false)
	{
		global $import_session, $mybb, $output, $module;

		$errors = $module->errors;
		if(empty($errors))
		{
			return;
		}

		$module->is_errors = true;

		if(!$this->doneheader && $inline == false)
		{
			$this->print_header("Error Encountered", '', 1);
		}

		$error_list = "";
		if(!is_array($errors))
		{
			$errors = array($errors);
		}

		$error_list = implode("</li>\n<li>", $errors);

		echo "<p>
		<div class=\"error\">
		<strong>The MyBB Merge System encountered the following problems:</strong><br />
		<ul>
		<li>{$error_list}</li>
		</ul>
		</div>
		Once you have resolved the mentioned issues, you may continue by pressing \"Next\" below.
		<br />
		<br />
		</p>";

		if($inline == false)
		{
			$output->print_footer($import_session['module'], 'module', 1);
		}
	}

	function print_per_screen_page($per_screen=10)
	{
		global $import_session, $mybb, $output, $module, $db;

		$module_name = str_replace(array("import_", ".", ".."), "", $import_session['module']);

		$module->trackers['start_'.$module_name] = 0;

		$db->write_query("REPLACE INTO ".TABLE_PREFIX."trackers SET count=".intval($this->trackers['start_'.$module_name]).", type='".$db->escape_string($module_name)."'");

		$this->calculate_stats(false);

		echo '
<div class="border_wrapper">
<div class="title">Options Configuration</div>
<table class="general" cellspacing="0">
<tr>
<th colspan="2" class="first last">Please select how many '.$module->settings['friendly_name'].' to import at a time:</th>
</tr>
<tr>
<td><label for="per_screen"> '.ucfirst($module->settings['friendly_name']).' to import at a time:
</label></td>
<td><input type="text" name="'.$module_name.'_per_screen" id="per_screen" value="'.intval($per_screen).'" style="width: 90%" /></td>
</tr>
<tr>
<th colspan="2" class="first last">Do you want to automatically continue to the next step until it\'s finished?:</th>
</tr>
<tr>
<td><label for="autorefresh_yes"> Yes
</label></td>
<td><input type="radio" name="autorefresh" id="autorefresh_yes" value="yes" checked="checked" /></td>
</tr>
<tr>
<td><label for="autorefresh_no"> No
</label></td>
<td><input type="radio" name="autorefresh" id="autorefresh_no" value="no" /></td>
</tr>';

		$import_session['autorefresh'] = "";
		$mybb->input['autorefresh'] = "no";

		$print_screen_func = "print_{$module_name}_per_screen_page";

		if(method_exists($module, $print_screen_func))
		{
			$module->$print_screen_func();
		}

		echo '</table></div><br />';

		$output->print_footer($import_session['module'], 'module', 1);
	}

	function calculate_stats($in_progress_stats=true)
	{
		global $import_session, $module;

		$module_name = str_replace(array("import_", ".", ".."), "", $import_session['module']);

		$left = $import_session['total_'.$module_name]-$module->trackers['start_'.$module_name]-$import_session[$module_name.'_per_screen'];

		if($import_session[$module_name.'_per_screen'] <= 0)
		{
			$pages = 0;
		}
		else
		{
			$pages = ceil(($left/$import_session[$module_name.'_per_screen']));
		}

		if($left <= 0)
		{
			$left = 0;
		}

		if($pages <= 0)
		{
			$pages = 0;
		}

		$importing_now = $import_session[$module_name.'_per_screen'];
		if($left < $importing_now && $pages == 0)
		{
			$importing_now = $import_session['total_'.$module_name]-$module->trackers['start_'.$module_name];
		}

		if($in_progress_stats == true)
		{
			echo "<i>".my_number_format($importing_now)." {$module->settings['friendly_name']} are importing right now. There are ".my_number_format($left)." {$module->settings['friendly_name']} left to import and ".my_number_format($pages)." pages left.</i><br /><br />";
		}
		else
		{
			echo "<i>There are ".my_number_format($import_session['total_'.$module_name])." {$module->settings['friendly_name']} that will be imported.</i><br /><br />";
		}
		flush();
	}

	function set_error_notice_in_progress($error_message)
	{
		$this->error_notice_in_progress = $error_message;
	}

	function construct_progress_bar()
	{
		if($this->_progress_bar_constructed == 1)
		{
			return;
		}

		echo "<div align=\"center\"><p class=\"progressBar\">
						<span><em id=\"progress_bar\">&nbsp;</em></span>
					</p>
					<span id=\"status_message\">&nbsp;</span></div>";
		flush();

		$this->update_progress_bar(0, "Loading data from database...");

		$this->_progress_bar_constructed = 1;
	}

	function update_progress_bar($left, $status_message="")
	{
		if($this->_last_left == $left && empty($status_message))
		{
			return;
		}

		echo "<script type=\"text/javascript\">";
		if($this->_last_left != $left)
		{
			echo " document.getElementById('progress_bar').style.left='{$left}px';";
		}

		if($status_message)
		{
			echo " document.getElementById('status_message').innerHTML='".str_replace("'", "\\'", $status_message)."';";
		}
		echo "</script>\n";
		flush();

		$this->_last_left = $left;
	}

	function print_progress($position, $id="")
	{
		global $import_session, $module;

		$module_name = str_replace(array("import_", ".", ".."), "", $import_session['module']);

		// Are we using the friendly progress bar or not?
		if($position == "start")
		{
			$this->_current_id = $id;
			if($this->_internal_counter == 0)
			{
				$this->construct_progress_bar();

				++$this->_internal_counter;
			}
		}
		else
		{
			// Make sure we have the right demoninator for finding the % of how far we're done on that one page.
			if($this->_progress_denominator == 0)
			{
				if($import_session[$module_name.'_per_screen'] > ($import_session['total_'.$module_name]-$module->trackers['start_'.$module_name]))
				{
					$this->_progress_denominator = $import_session['total_'.$module_name]-$module->trackers['start_'.$module_name];
				}
				else
				{
					$this->_progress_denominator = $import_session[$module_name.'_per_screen'];
				}
			}

			// If it is still == 0 then don't cause division by 0 error (we shouldn't normally get this, but if we do then there is a bug and we should gracefully handle it)
			if($this->_progress_denominator == 0)
			{
				$left = 200;
			}
			else
			{
				$percent_done = $this->_internal_counter/$this->_progress_denominator;
				$left = round($percent_done*200, 0);
			}

			if($import_session[$module_name.'_per_screen'] > 1000)
			{
				$modulus = round(($import_session[$module_name.'_per_screen']/1000), 0);
			}
			else
			{
				$modulus = 1;
			}

			if($import_session[$module_name.'_per_screen'] <= 1000 || ($import_session[$module_name.'_per_screen'] > 1000 && ($this->_internal_counter % $modulus) == 0))
			{
				// If we're merging a user
				if($position == "merge_user")
				{
					$status_message = "Merging user #{$id['import_uid']} with user #{$id['duplicate_uid']}";
				}
				else
				{

					if($this->_friendly_name_singular == "")
					{
						// Removes the "s" from the friendly name to make it singular
						$this->_friendly_name_singular = substr($module->settings['friendly_name'], 0, -1);
					}

					// Settings are special case
					if($import_session['module'] == "settings")
					{
						$status_message = "Inserting {$this->_friendly_name_singular} {$this->_current_id} from your other {$module->plain_bbname} database";
					}
					else if(!is_numeric($this->_current_id))
					{
						$status_message = $this->_current_id;
					}
					else
					{
						$status_message = "Inserting {$this->_friendly_name_singular} #{$this->_current_id}";
					}
				}

				if($percent_done >= 1)
				{
					$status_message = "Please wait... ";
				}
			}

			$this->update_progress_bar($left, $status_message);

			++$this->_internal_counter;
		}
	}

	function print_none_left_message()
	{
		global $module, $import_session;

		echo "<div class=\"alert\">";
		if($import_session['module'] == "import_settings")
		{
			echo "There are no ".$module->settings['friendly_name']." to update. Please press next to continue.";
		}
		else
		{
			echo "There are no ".$module->settings['friendly_name']." to import. Please press next to continue.";
		}
		echo "</div>";
		define("BACK_BUTTON", false);
	}
}
?>