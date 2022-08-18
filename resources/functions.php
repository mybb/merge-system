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

/**
 * Updates the import session cache which contains: stats, completed modules, paused modules, current modules, etc
 *
 */
function update_import_session()
{
	global $import_session, $cache, $board, $db;

	if(!$import_session['completed'])
	{
		$import_session['completed'] = array();
	}
	if(!$import_session['disabled'])
	{
		$import_session['disabled'] = array();
	}

	// Stats
	if(!empty($board->old_db->query_count))
	{
		$import_session['olddb_query_count'] += $board->old_db->query_count;
	}
	$import_session['newdb_query_count'] += $db->query_count;
	$import_session['total_query_time'] += $db->query_time;

	$import_session['completed'] = array_unique($import_session['completed']);
	$import_session['disabled'] = array_unique($import_session['disabled']);

	// To prevent the cache content being too long, remove it upon saving.
	unset($import_session['column_length']);

	$cache->update("import_cache", $import_session);

	if(WRITE_LOGS == 1)
	{
		global $debug;

		$debug_import_session = $import_session;

		// Remove private information
		unset($debug_import_session['old_db_host']);
		unset($debug_import_session['old_db_user']);
		unset($debug_import_session['old_db_pass']);
		unset($debug_import_session['old_db_name']);
		unset($debug_import_session['old_tbl_prefix']);
		unset($debug_import_session['connect_config']);

		$debug->log->datatrace('$debug_import_session', my_serialize($debug_import_session));
	}
}

/*
 * Converts a yes/no string into a 1/0 integer
 * @param string Corresponding to yes or no
 * @param string yes or no. Tells the function how to process it
 * @return int Corresponding to 1 or 0
 */
function yesno_to_int($setting, $yes="yes")
{
	if(is_integer($setting))
	{
		return $setting;
	}

	if($setting == "no" && $yes == "yes")
	{
		return 0;
	}
	elseif($setting == "yes" && $yes == "yes")
	{
		return 1;
	}
	elseif($setting == "no" && $yes == "no")
	{
		return 1;
	}
	elseif($setting == "yes" && $yes == "no")
	{
		return 0;
	}
	else
	{
		return 0;
	}
}

/**
 * Reverses an 1/0 integer
 * @param int Integer to be converted
 * @return string Correspondig no or yes
 */
function int_to_01($var)
{
	return int_to_yes_no($var, 0);
}

/**
 * Converts an 1/0 integer to yes/no
 * @param int $setting Integer to be converted
 * @param bool|int $yes Whether 0 is yes or not
 * @return int Correspondig 1 or 0. Tells the function how to process it
 */
function int_to_yes_no($setting, $yes=1)
{
	$setting = intval($setting);

	if($setting == 0 && $yes == 1)
	{
		return 0;
	}
	elseif($setting == 1 && $yes == 1)
	{
		return 1;
	}
	elseif($setting == 0 && $yes == 0)
	{
		return 1;
	}
	elseif($setting == 1 && $yes == 0)
	{
		return 0;
	}
	else
	{
		return 1;
	}
}

/**
 * Convert an integer 1/0 into text on/off
 * @param int $setting Integer to be converted
 * @param bool|int $on whether 1 is on or not
 * @return string Correspondig on or off
 */
function int_to_on_off($setting, $on=1)
{
	$setting = intval($setting);

	if($setting == 0 && $on == 1)
	{
		return "off";
	}
	elseif($setting == 1 && $on == 1)
	{
		return "on";
	}
	elseif($setting == 0 && $on == 0)
	{
		return "on";
	}
	elseif($setting == 1 && $on == 0)
	{
		return "off";
	}
	else
	{
		return "on";
	}
}

/**
 * Return a formatted list of errors
 *
 * @param array $array Errors
 * @return string Formatted errors list
 */
function error_list($array)
{
	$string = "<ul>\n";
	foreach($array as $error)
	{
		$string .= "<li>{$error}</li>\n";
	}
	$string .= "</ul>\n";
	return $string;
}

/**
 * Remove the temporary importing data fields we use to keep track of, for example, vB's imported user id, etc.
 *
 * @param boolean $text Show text progress
 */
function delete_import_fields($text=true)
{
	global $db, $output, $lang;

	if($text == true)
	{
		$output->construct_progress_bar();
	}

	if($text == true)
	{
		$output->update_progress_bar(0, $lang->sprintf($lang->removing_table, TABLE_PREFIX."trackers"));
	}
	$db->drop_table("trackers");

	$drop_list = array(
		"users" => array('import_uid', 'import_usergroup', 'import_additionalgroups', 'import_displaygroup'),
		"forums" => array('import_fid', 'import_pid'),
		"threads" => array('import_tid', 'import_uid', 'import_poll', 'import_firstpost'),
		"posts" => array('import_pid', 'import_uid'),
		"polls" => array('import_pid', 'import_tid'),
		"usergroups" => array('import_gid'),
		"attachments" => array('import_aid'),
	);

	$increment = 200/(count($drop_list, COUNT_RECURSIVE)-count($drop_list));
	$progress = 0;
	foreach($drop_list as $table => $columns)
	{
		if($text == true)
		{
			$columns_list = implode(', ', $columns);
			$output->update_progress_bar($progress, $lang->sprintf($lang->removing_columns, $columns_list, TABLE_PREFIX.$table));
			$progress += $increment;
		}

		$columns_to_drop = array();
		foreach($columns as $column)
		{
			if($db->field_exists($column, $table))
			{
				$columns_to_drop[] = $column;
			}
		}
		if(!empty($columns_to_drop))
		{
			if($db->type == "sqlite")
			{
				// Can be achieved in a transaction if we'd finally support it.
				foreach($columns_to_drop as $column)
				{
					$db->write_query("ALTER TABLE ".TABLE_PREFIX.$table." DROP {$column}");
				}
			}
			else
			{
				$columns_sql = implode(", DROP ", $columns_to_drop);
				$db->write_query("ALTER TABLE ".TABLE_PREFIX.$table." DROP {$columns_sql}");
			}
		}
	}

	$db->delete_query("datacache", "title='import_cache'");
}

/**
 * Create the temporary importing data fields we use to keep track of, for example, vB's imported user id, etc.
 *
 * @param boolean $text Show text progress
 */
function create_import_fields($text=true)
{
	global $db, $output, $lang;

	if($text == true)
	{
		$output->construct_progress_bar();

		echo "<br />{$lang->creating_fields}";
		flush();
	}

	// First clear all.
	delete_import_fields(false);

	if($text == true) {
		$output->update_progress_bar(0, $lang->sprintf($lang->creating_table, TABLE_PREFIX."trackers"));
	}

	if($db->type == "mysql" || $db->type == "mysqli")
	{
		$createtable_trackers_sql_table_engine = " ENGINE=MyISAM";
	}
	else
	{
		$createtable_trackers_sql_table_engine = "";
	}
	$createtable_trackers_sql = "CREATE TABLE ".TABLE_PREFIX."trackers (
	  type varchar(20) NOT NULL DEFAULT '',
	  count int NOT NULL DEFAULT '0',
	  PRIMARY KEY (type)
	){$createtable_trackers_sql_table_engine};";

	$db->write_query($createtable_trackers_sql);

	$add_list = array(
		"int" => array(
			"users" => array('import_uid', 'import_usergroup', 'import_displaygroup'),
			"forums" => array('import_fid', 'import_pid'),
			"threads" => array('import_tid', 'import_uid', 'import_poll', 'import_firstpost'),
			"posts" => array('import_pid', 'import_uid'),
			"polls" => array('import_pid', 'import_tid'),
			"usergroups" => array('import_gid'),
			"attachments" => array('import_aid'),
		),
		"text" => array(
			"users" => array('passwordconvert', 'passwordconverttype', 'passwordconvertsalt', 'import_additionalgroups'),
		),
	);

	$increment = 0;
	foreach($add_list as $array)
	{
		$increment += (count($array, COUNT_RECURSIVE)-count($array));
	}

	$increment = 200/$increment;
	$progress = 0;
	foreach($add_list['int'] as $table => $columns)
	{
		if($text == true)
		{
			$columns_list = implode(', ', $columns);
			$output->update_progress_bar($progress, $lang->sprintf($lang->creating_columns, "int", $columns_list, TABLE_PREFIX.$table));
			$progress += $increment;
		}

		$columns_to_add = array();
		foreach($columns as $column)
		{
			if(!$db->field_exists($column, $table))
			{
				$columns_to_add[] = $column;
			}
		}
		if(!empty($columns_to_add))
		{
			if($db->type == "sqlite")
			{
				// Can be achieved in a transaction if we'd finally support it.
				foreach($columns_to_add as $column)
				{
					$db->write_query("ALTER TABLE ".TABLE_PREFIX.$table." ADD {$column} int NOT NULL DEFAULT '0'");
				}
			}
			else
			{
				$columns_sql = implode(" int NOT NULL default '0', ADD ", $columns_to_add);
				$db->write_query("ALTER TABLE ".TABLE_PREFIX.$table." ADD {$columns_sql} int NOT NULL DEFAULT '0'");

				if($db->type == "mysql" || $db->type == "mysqli")
				{
					foreach($columns as $column)
					{
						$db->write_query("ALTER TABLE ".TABLE_PREFIX.$table." ADD INDEX ( `{$column}` )");
					}
				}
			}
		}
	}

	foreach($add_list['text'] as $table => $columns)
	{
		if($text == true)
		{
			$columns_list = implode(', ', $columns);
			$output->update_progress_bar($progress, $lang->sprintf($lang->creating_columns, "text", $columns_list, TABLE_PREFIX.$table));
			$progress += $increment;
		}

		$columns_to_add = array();
		foreach($columns as $column)
		{
			if(!$db->field_exists($column, $table))
			{
				$columns_to_add[] = $column;
			}
		}
		if(!empty($columns_to_add))
		{
			if($db->type == "sqlite")
			{
				// Can be achieved in a transaction if we'd finally support it.
				foreach($columns_to_add as $column)
				{
					$db->write_query("ALTER TABLE ".TABLE_PREFIX.$table." ADD {$column} text");
				}
			}
			else
			{
				$columns_sql = implode(" text, ADD ", $columns_to_add);
				$db->write_query("ALTER TABLE ".TABLE_PREFIX.$table." ADD {$columns_sql} text");
			}
		}
	}

	if($text == true)
	{
		$output->update_progress_bar(200, $lang->please_wait);
		echo " {$lang->done}<br />\n";
		flush();
	}
}

/**
 * Properly converts the encoding of a string based upon the old table to the new table to utf8 encoding, as best as we can
 *
 * @param string $text The text to convert
 * @param string $old_table_name The old table (e.x. vB's user table)
 * @param string $new_table_name The new table (e.x. MyBB's user table)
 * @return string The converted text in utf8 format
 */
function encode_to_utf8($text, $old_table_name, $new_table_name)
{
    global $import_session, $db, $module;

	if($import_session['encode_to_utf8'] == 0)
	{
		return $text;
	}

	$old_table_name = OLD_TABLE_PREFIX.$old_table_name;
	$new_table_name = TABLE_PREFIX.$new_table_name;

    // Get the character set if needed
    if(empty($import_session['table_charset_old'][$old_table_name]) || empty($import_session['table_charset_new'][$new_table_name]))
    {
		$old_table_prefix = $db->table_prefix;
		$db->set_table_prefix('');

		$old_old_db_table_prefix = $module->old_db->table_prefix;
		$module->old_db->set_table_prefix('');

        $table = $module->old_db->show_create_table($old_table_name);
        preg_match("#CHARSET=(\S*)#i", $table, $old_charset);

        $table = $db->show_create_table($new_table_name);
        preg_match("#CHARSET=(\S*)#i", $table, $new_charset);

		$db->set_table_prefix($old_table_prefix);
		$module->old_db->set_table_prefix($old_old_db_table_prefix);

        $import_session['table_charset_old'][$old_table_name] = $old_charset[1];
        $import_session['table_charset_new'][$new_table_name] = $new_charset[1];
    }

    // Convert as needed
    if(($import_session['table_charset_new'][$new_table_name] != $import_session['table_charset_old'][$old_table_name]
	|| check_encoding($text, fetch_iconv_encoding($import_session['table_charset_new'][$new_table_name])) === false)
    && $import_session['table_charset_old'][$old_table_name] != ''
    && $import_session['table_charset_new'][$new_table_name] != '')
    {
        if(!function_exists('iconv'))
        {
            if(fetch_iconv_encoding($import_session['table_charset_old'][$old_table_name]) != 'iso-8859-1' || !function_exists("utf8_encode"))
            {
                return $text;
            }

			return utf8_encode($text);
        }

		$converted_str = iconv(fetch_iconv_encoding($import_session['table_charset_old'][$old_table_name]), fetch_iconv_encoding($import_session['table_charset_new'][$new_table_name]).'//TRANSLIT', $text);

		// Do we have bad characters? (i.e. db/table encoding set to UTF-8 but string is actually ISO)
		if(my_strlen($converted_str) < my_strlen($text))
		{
			// Was our database/tables set to UTF-8 encoding and the data actually in iso encoding?
			// Stop trying to confuse us!!
			$converted_str = iconv("iso-8859-1", fetch_iconv_encoding($import_session['table_charset_new'][$new_table_name]).'//IGNORE', $text);
			if(my_strlen($converted_str) >= my_strlen($text))
			{
				return $converted_str;
			}
		}

		// Try to convert, but don't stop when a character cannot be converted
		return iconv(fetch_iconv_encoding($import_session['table_charset_old'][$old_table_name]), fetch_iconv_encoding($import_session['table_charset_new'][$new_table_name]).'//IGNORE', $text);
	}

	return $text;
}

/**
 * Converts the given MySQL encoding to a PHP iconv usable encoding
 *
 * @param string $mysql_encoding The MySQL encoding
 * @return string The iconv encoding
 */
function fetch_iconv_encoding($mysql_encoding)
{
	$mysql_encoding = explode("_", $mysql_encoding);
	switch($mysql_encoding[0])
	{
		case "utf8":
		case "utf8mb4":
			return "utf-8";
			break;
		case "latin1":
			return "iso-8859-1";
			break;
		default:
			return $mysql_encoding[0];
    }
}

/**
 * Builds a CSV parent list for a particular forum.
 *
 * @param int $fid The forum ID
 * @param string $navsep Optional separator - defaults to comma for CSV list
 * @param string $parent_list
 * @return string The built parent list
 */
function make_parent_list($fid, $navsep=",", $parent_list="")
{
   global $pforumcache, $db;

   if(!$pforumcache)
   {
       $query = $db->simple_select("forums", "fid, import_fid, import_pid", "import_fid > 0", array("order_by" => "import_pid"));
       while($forum = $db->fetch_array($query))
       {
			$pforumcache[$forum['import_fid']] = array(
				"fid" => $forum['fid'],
				"import_pid" => $forum['import_pid'],
			);
       }
   }

	if(is_array($pforumcache[$fid]))
	{
		if($pforumcache[$fid]['import_pid'] && $pforumcache[$pforumcache[$fid]['import_pid']])
		{
			$parent_list = make_parent_list($pforumcache[$fid]['import_pid'], $navsep, $parent_list).$parent_list;
		}

		if($parent_list)
		{
			$parent_list .= ',';
		}

		$parent_list .= $pforumcache[$fid]['fid'];
	}

	return $parent_list;
}

/**
 * Builds a CSV parent list for a particular forum.
 *
 * @param int $fid The forum ID
 * @param string $navsep Optional separator - defaults to comma for CSV list
 * @param string $parent_list
 * @return string The built parent list
 */
function make_parent_list_pid($fid, $navsep=",", $parent_list="")
{
   global $pforumcache, $db;

   if(!$pforumcache)
   {
       $query = $db->simple_select("forums", "fid, pid", "import_fid > 0", array("order_by" => "pid"));
       while($forum = $db->fetch_array($query))
       {
			$pforumcache[$forum['fid']] = array(
				"fid" => $forum['fid'],
				"pid" => $forum['pid']
			);
       }
   }

	if(is_array($pforumcache[$fid]))
	{
		if($pforumcache[$fid]['pid'] && $pforumcache[$pforumcache[$fid]['pid']])
		{
			$parent_list = make_parent_list_pid($pforumcache[$fid]['pid'], $navsep, $parent_list).$parent_list;
		}

		if($parent_list)
		{
			$parent_list .= ',';
		}

		$parent_list .= $pforumcache[$fid]['fid'];
	}

	return $parent_list;
}

/**
 * Checks for the existance of a file via url (via http status code)
 *
 * @param string $url The link to the url
 * @return boolean Whether or not the url exists
 */
function check_url_exists($url)
{
	if(!$url)
	{
		return false;
	}

	$url_parsed = @parse_url($url);

	if($url_parsed === false)
	{
		return false;
	}

	$url_parsed = array_map('trim', $url_parsed);
	$url_parsed['port'] = (!isset($url_parsed['port'])) ? 80 : (int)$url_parsed['port'];

	if(!isset($url_parsed['host']))
	{
		return false;
	}

	$headers = @get_headers("$url_parsed[scheme]://$url_parsed[host]:$url_parsed[port]{$url_parsed['path']}");

	$status = 0;
	if(preg_match('#HTTP[/]1.?[0-9]{1,} ?([0-9]{3}) ?(.*)#i', $headers[0], $matches))
	{
		$status = $matches[1];
	}

	if($status >= 200 & $status < 300)
	{
		return true;
	}

	return false;
}

/**
 * Fetch the contents of a remote file.
 *
 * @param string $url The URL of the remote file
 * @param array $post_data
 * @return string The remote file contents.
 */
function merge_fetch_remote_file($url, $post_data=array())
{
	$post_body = '';
	if(!empty($post_data))
	{
		foreach($post_data as $key => $val)
		{
			$post_body .= '&'.urlencode($key).'='.urlencode($val);
		}
		$post_body = ltrim($post_body, '&');
	}

	// Use fopen if we have an internal path
	// Internal pathes start either with a '.' (relative), a '/' (UNIX) or 'X:\' where X can be anything. Also Windows can be used with a slash instead of a backslash
	if(my_substr($url, 0, 1) == '.' || my_substr($url, 0, 1) == '/' || my_substr($url, 1, 2) == ':\\' || my_substr($url, 1, 2) == ':/')
	{
		@clearstatcache();
		if(is_readable($url))
		{
			$ch = @fopen($url, 'rb');
			$data = @fread($ch, filesize($url));
			@fclose($ch);
			return $data;
		}
	}

	if(function_exists("curl_init"))
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		if(!empty($post_body))
		{
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $post_body);
		}
		$data = curl_exec($ch);
		curl_close($ch);
		return $data;
	}
	else if(function_exists("fsockopen"))
	{
		$url = @parse_url($url);
		if(!$url['host'])
		{
			return false;
		}
		if(!$url['port'])
		{
			$url['port'] = 80;
		}
		if(!$url['path'])
		{
			$url['path'] = "/";
		}
		if($url['query'])
		{
			$url['path'] .= "?{$url['query']}";
		}
		$fp = @fsockopen($url['host'], $url['port'], $error_no, $error, 10);
		@stream_set_timeout($fp, 10);
		if(!$fp)
		{
			return false;
		}
		$headers = array();
		if(!empty($post_body))
		{
			$headers[] = "POST {$url['path']} HTTP/1.0";
			$headers[] = "Content-Length: ".strlen($post_body);
			$headers[] = "Content-Type: application/x-www-form-urlencoded";
		}
		else
		{
			$headers[] = "GET {$url['path']} HTTP/1.0";
		}

		$headers[] = "Host: {$url['host']}";
		$headers[] = "Connection: Close";
		$headers[] = "\r\n";

		if(!empty($post_body))
		{
			$headers[] = $post_body;
		}

		$headers = implode("\r\n", $headers);
		if(!@fwrite($fp, $headers))
		{
			return false;
		}
		$data = "";
		while(!feof($fp))
		{
			$data .= fgets($fp, 12800);
		}
		fclose($fp);
		$data = explode("\r\n\r\n", $data, 2);
		return $data[1];
	}
	else if(empty($post_data))
	{
		return @implode("", @file($url));
	}
	else
	{
		return false;
	}
}

if(!function_exists('htmlspecialchars_decode'))
{
	/**
	 * Decodes a string of html special characters
	 *
	 * @param string $text The encoded string of html special characters
	 * @return string  The decoded string of html special characters
	 */
	function htmlspecialchars_decode($text)
	{
		return strtr($text, array_flip(get_html_translation_table(HTML_SPECIALCHARS)));
	}
}

/**
 * Returns any html entities to their original character.
 *
 * @param string $string The string to un-htmlentitize.
 * @return int The un-htmlentitied' string.
 */
function utf8_unhtmlentities($string)
{
	// Replace numeric entities
	$string = preg_replace_callback('~&#x([0-9a-f]+);~i', create_function('$matches', 'return unichr(hexdec($matches[1]));'), $string);
	$string = preg_replace_callback('~&#([0-9]+);~', create_function('$matches', 'return unichr($matches[1]);'), $string);

	// Replace literal entities
	$trans_tbl = get_html_translation_table(HTML_ENTITIES);
	$trans_tbl = array_flip($trans_tbl);

	return strtr($string, $trans_tbl);
}

if(!function_exists('unichr'))
{
	/**
	 * Returns any ascii to it's character (utf-8 safe).
	 *
	 * @param string $c The ascii to characterize.
	 * @return int The characterized ascii.
	 */
	function unichr($c)
	{
		// Covers first 127 ASCII characters
		if($c <= 0x7F)
		{
			return chr($c);
		}
		// Covers ASCII characters and combinations all the way up to 2047. Most non-standard characters should fall in this range
		else if($c <= 0x7FF)
		{
			return chr(0xC0 | $c >> 6) . chr(0x80 | $c & 0x3F);
		}
		else if($c <= 0xFFFF)
		{
			return chr(0xE0 | $c >> 12) . chr(0x80 | $c >> 6 & 0x3F)
										. chr(0x80 | $c & 0x3F);
		}
		else if($c <= 0x10FFFF)
		{
			return chr(0xF0 | $c >> 18) . chr(0x80 | $c >> 12 & 0x3F)
										. chr(0x80 | $c >> 6 & 0x3F)
										. chr(0x80 | $c & 0x3F);
		}
		else
		{
			return false;
		}
	}
}

/**
 * Checks the encoding of a string (currently only supports utf-8)
 *
 * @param string $string The string to check
 * @param string $encoding the encoding to check against
 * @return mixed true on success, false on failure, -1 on unknown (couldn't detect)
**/
function check_encoding($string, $encoding)
{
	if(strlen($string) == 0)
	{
        return true;
    }

	if(strtolower($encoding) != "utf-8")
	{
		return -1;
	}

	// These functions can have significant load or crash if the string passed is too long.
	if(SKIP_ENCODING_DETECTION != 1 && strlen($string) < 1024*5)
	{
		return (preg_match('#^(?:
              [\x09\x0A\x0D\x20-\x7E]
            | [\xC2-\xDF][\x80-\xBF]
            |  \xE0[\xA0-\xBF][\x80-\xBF]
            | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}
            |  \xED[\x80-\x9F][\x80-\xBF]
            |  \xF0[\x90-\xBF][\x80-\xBF]{2}
            | [\xF1-\xF3][\x80-\xBF]{3}
            |  \xF4[\x80-\x8F][\x80-\xBF]{2}
        )*$#xs', $string) != 0);
	}

	return (preg_match('#^.{1}#us', $string) == 1);
}

/**
 * Checks and Attempts to allocate more memory if needed
 *
 * @return boolean true on success, false on failure
**/
function check_memory()
{
	$memory_usage = get_memory_usage();
	if(!$memory_usage)
	{
		return false;
	}

	$memory_limit = @ini_get("memory_limit");
	if(!$memory_limit || $memory_limit == -1)
	{
		return false;
	}

	preg_match("#^([0-9]+)\s?([kmg])b?$#i", trim(my_strtolower($memory_limit)), $matches);
	$memory_limit = 0;
	if($matches[1] && $matches[2])
	{
		switch($matches[2])
		{
			case "k":
				$memory_limit = $matches[1] * 1024;
				break;
			case "m":
				$memory_limit = $matches[1] * 1048576;
				break;
			case "g":
				$memory_limit = $matches[1] * 1073741824;
		}
	}
	$current_usage = get_memory_usage();
	$free_memory = $memory_limit - $current_usage;

	// Do we have less then 2 MB's left?
	if($free_memory < 2097152)
	{
		if($matches[1] && $matches[2])
		{
			switch($matches[2])
			{
				case "k":
					$memory_limit = (($memory_limit+2097152) / 1024)."K";
					break;
				case "m":
					$memory_limit = (($memory_limit+2097152) / 1048576)."M";
					break;
				case "g":
					$memory_limit = (($memory_limit+2097152) / 1073741824)."G";
			}
		}

		@ini_set("memory_limit", $memory_limit);
	}

	return true;
}

function my_friendly_time($timestamp)
{
	$timestamp = floor($timestamp);

	$years = floor($timestamp/31104000);
	$timestamp -= $years*31104000;

	$months = floor($timestamp/2592000);
	$timestamp -= $months*2592000;

	$days = floor($timestamp/86400);
	$timestamp -= $days*86400;

	$hours = floor($timestamp/3600);
	$timestamp -= $hours*3600;

	$minutes = floor($timestamp/60);
	$timestamp -= $minutes*60;

	$seconds = $timestamp;

	$string = $comma = "";
	if($years)
	{
		$string .= "{$years} years";
		$comma = ", ";
	}

	if($months)
	{
		$string .= "{$comma}{$months} months";
		$comma = ", ";
	}

	if($days)
	{
		$string .= "{$comma}{$days} days";
		$comma = ", ";
	}

	if($hours)
	{
		$string .= "{$comma}{$hours} hours";
		$comma = ", ";
	}

	if($minutes)
	{
		$string .= "{$comma}{$minutes} minutes";
		$comma = ", ";
	}

	if($seconds)
	{
		$string .= "{$comma}{$seconds} seconds";
	}

	return $string;
}

// Converts a string format to MyBB's date format
function get_date_format($format, $add='')
{
	if(strpos($format, "{$add}d {$add}M {$add}Y") !== FALSE)
	{
		$dateformat = 11;
	}
	elseif(strpos($format, "{$add}D {$add}M {$add}d") !== FALSE)
	{
		$dateformat = 10;
	}
	elseif (strpos($format, "{$add}j{$add}S") !== FALSE)
	{
		$dateformat = 9;
	}
	else
	{
		$dateformat = 10;
	}

	return $dateformat;
}

// Converts a string format to MyBB's time format
function get_time_format($format, $add='')
{
	if(strpos($format, "{$add}H:{$add}i") !== FALSE)
	{
		$timeformat = 3;
	}
	elseif (strpos($format, "{$add}g:{$add}i") !== FALSE)
	{
		$timeformat = 1;
	}
	else
	{
		$timeformat = 1;
	}

	return $timeformat;
}

// Converts a String timezone (Europe/Berlin) to a MyBB number
function get_timezone($zone)
{
	try {
		$time = new DateTime('now', new DateTimeZone($zone));
		$off = $time->format('P');
	} catch(Exception $e) {
		return '';
	}
	
	list($h, $m) = explode(":", $off);
	
	$v = substr($h, 0, 1);
	$h = substr($h, 1);
	
	if(substr($h, 0, 1) == 0)
	    $h = substr($h, 1);
	
	if($m == 30)
	    $h .= ".5";
	else if($m == 45)
	    $h .= ".75";
	
	if($v == "-")
	    $h = "-{$h}";
	
	return $h;
}

define('SQL_TINYTEXT', 255);
define('SQL_TEXT', 65535);
define('SQL_MEDIUMTEXT', 16777215);
define('SQL_LONGTEXT', 4294967295);

/**
 * @deprecated
 * Returns an array of length informations about one table
 *
 * @param string $table Which table should be checked
 * @param bool $cache Whether or not the array should be cached. Default is true
 *
 * @return array
 */
function get_length_info($table, $cache=true)
{
	global $import_session, $db;

	if(isset($import_session['column_length'][$table]) && $cache) {
		return $import_session['column_length'][$table];
	}

	$lengthinfo = array();
	$fieldinfo = $db->show_fields_from($table);

	foreach($fieldinfo as $field) {
		if($field['Type'] == 'tinytext') {
			$length = SQL_TINYTEXT;
		} elseif($field['Type'] == 'text' || $field['Type'] == 'blob') {
			$length = SQL_TEXT;
		} elseif($field['Type'] == 'mediumtext' || $field['Type'] == 'mediumblob') {
			$length = SQL_MEDIUMTEXT;
		} elseif($field['Type'] == 'longtext' || $field['Type'] == 'longblob') {
			$length = SQL_LONGTEXT;
		} else {
			preg_match('#\(([0-9]*)\)#', $field['Type'], $matches);
			$length = (int)$matches[1];
		}

		$lengthinfo[$field['Field']] = $length;
	}

	if($cache) {
		$import_session['column_length'][$table] = $lengthinfo;
	}

	return $lengthinfo;
}

/**
 * Returns an array of data types and length/limit information of each column in a table
 *
 * @param string $table Which table should be checked.
 * @param bool $cache Whether to cache the returned array. Default is true.
 * @param bool $hard Whether to fetch the information from the database system. Default is false.
 *
 * @return array
 */
function get_column_length_info($table, $cache=true, $hard=false)
{
	global $import_session, $db;

	if(isset($import_session['column_length'][$table]) && !$hard)
	{
		return $import_session['column_length'][$table];
	}

	$table_columninfo = array();

	$columns = $db->show_fields_from($table);

	foreach($columns as $column)
	{
		$columninfo = array(
			// (default) The data type can't be parsed by the Merge System.
			'type' => MERGE_DATATYPE_UNKNOWN,
			// The data type from the table definition.
			'def_type' => $column['Type'],
		);
		$column_type = strtolower($column['Type']);

		// Follow SQLite's type affinity to figure out our data types.
		if($db->type == 'sqlite')
		{
			// The column data type from the table definition.
			$column_type_table = $column_type;

			// We'll also try to find what the column type was defined at the table creation.
			if(preg_match('#([^()]*)?((?:\(([0-9,]*)\))|)#', $column_type, $column_type_matches) !== false)
			{
				$column_type_table = trim($column_type_matches[1]);

				$column_type_arg = null;
				$column_type_arg_1 = null;
				$column_type_arg_2 = null;

				// We got a parenthesised argument.
				if (!empty($column_type_matches[2]))
				{
					$column_type_arg = trim($column_type_matches[3]);

					if (strpos($column_type_arg, ',') !== false)
					{
						list($column_type_arg_1, $column_type_arg_2) = explode(',', $column_type_arg, 2);
						$column_type_arg_1 = (int)trim($column_type_arg_1);
						$column_type_arg_2 = (int)trim($column_type_arg_2);
					}
					else
					{
						$column_type_arg = (int)$column_type_arg;
					}
				}
			}

			// If the declared type contains the string "INT" then it is assigned INTEGER affinity.
			if(strpos($column_type, 'int') !== false)
			{
				$columninfo['type'] = MERGE_DATATYPE_INT;

				// SQLite's INITEGER type can store as large as an 8 bytes signed integer.
				$columninfo['min'] = MERGE_DATATYPE_INT_BIGINT_MIN;
				$columninfo['max'] = MERGE_DATATYPE_INT_BIGINT_MAX;

				// The table suggests it as a BOOLEAN column created by MyBB.
				if($column_type_table == 'tinyint(1)')
				{
					$columninfo['type_table'] = MERGE_DATATYPE_BOOL;
					$columninfo['min_table'] = 0;
					$columninfo['max_table'] = 1;
				}
				// Or MyBB will consider it a regular *signed* INTEGER column.
				else
				{
					switch ($column_type_table)
					{
						case 'tinyint':
							$int_type_min = 'MERGE_DATATYPE_INT_TINYINT_MIN';
							$int_type_max = 'MERGE_DATATYPE_INT_TINYINT_MAX';
							break;
						case 'smallint':
							$int_type_min = 'MERGE_DATATYPE_INT_SMALLINT_MIN';
							$int_type_max = 'MERGE_DATATYPE_INT_SMALLINT_MAX';
							break;
						case 'mediumint':
							$int_type_min = 'MERGE_DATATYPE_INT_MEDIUMINT_MIN';
							$int_type_max = 'MERGE_DATATYPE_INT_MEDIUMINT_MAX';
							break;
						case 'bigint':
							$int_type_min = 'MERGE_DATATYPE_INT_BIGINT_MIN';
							$int_type_max = 'MERGE_DATATYPE_INT_BIGINT_MAX';
							break;
						case 'int':
						case 'integer':
						default:
							$int_type_min = 'MERGE_DATATYPE_INT_INTEGER_MIN';
							$int_type_max = 'MERGE_DATATYPE_INT_INTEGER_MAX';
					}

					$columninfo['min_table'] = constant($int_type_min);
					$columninfo['max_table'] = constant($int_type_max);
				}
			}
			// If the declared type contains "CHAR", "CLOB", or "TEXT" then that column has TEXT affinity.
			else if(strpos($column_type, 'char') !== false || strpos($column_type, 'clob') !== false || strpos($column_type, 'text') !== false)
			{
				$columninfo['type'] = MERGE_DATATYPE_CHAR;
				$columninfo['length_type'] = MERGE_DATATYPE_CHAR_LENGTHTYPE_BYTE;
				$columninfo['length'] = MERGE_DATATYPE_CHAR_TEXT_LENGTH_SQLITE;

				// The table suggests it should be a CHAR(n) or VARCHAR(n) column.
				if(!is_null($column_type_arg) && ($column_type_table == 'char' || $column_type_table == 'varchar'))
				{
					$columninfo['length_table'] = $column_type_arg;
					$columninfo['length_type_table'] = MERGE_DATATYPE_CHAR_LENGTHTYPE_CHAR;
				}
				// Or it's declared as a TEXT column.
				else if(strpos($column_type_table, 'text') !== false)
				{
					switch($column_type_table)
					{
						case 'tinytext':
							$char_type_length = MERGE_DATATYPE_CHAR_TINYTEXT_LENGTH;
							break;
						case 'mediumtext':
							$char_type_length = MERGE_DATATYPE_CHAR_MEDIUMTEXT_LENGTH;
							break;
						case 'longtext':
							$char_type_length = MERGE_DATATYPE_CHAR_LONGTEXT_LENGTH;
							break;
						case 'text':
						default:
							$char_type_length = MERGE_DATATYPE_CHAR_TEXT_LENGTH;
					}

					$columninfo['length_table'] = $char_type_length;
					$columninfo['length_type_table'] = MERGE_DATATYPE_CHAR_LENGTHTYPE_BYTE;
				}
			}
			// If the declared type for a column contains "BLOB" or if no type is specified then the column has affinity BLOB.
			else if(strpos($column_type, 'blob') !== false || empty($column_type))
			{
				$columninfo['type'] = MERGE_DATATYPE_BIN;
				$columninfo['length'] = MERGE_DATATYPE_BIN_BLOB_LENGTH_SQLITE;

				// It's declared as a BLOB(n) column.
				if(!is_null($column_type_arg) && $column_type_table == 'blob')
				{
					$columninfo['length_table'] = $column_type_arg;
				}
			}
			// If the declared type contains "REAL", "FLOA", or "DOUB" then the column has REAL affinity.
			else if(strpos($column_type, 'real') !== false || strpos($column_type, 'floa') !== false || strpos($column_type, 'doub') !== false)
			{
				$columninfo['type'] = MERGE_DATATYPE_DBL;
			}
			// Otherwise, the affinity is NUMERIC.
			else
			{
				$columninfo['type'] = MERGE_DATATYPE_FIXED;

				// It's declared as a NUMERIC(n) column.
				if(!is_null($column_type_arg) && ($column_type_table == 'numeric' || $column_type_table == 'decimal'))
				{
					$columninfo['length_table'] = $column_type_arg_1;
					$columninfo['scale_table'] = $column_type_arg_2;
				}
				else
				{
					$columninfo['length'] = -1;
					$columninfo['scale'] = -1;
				}
			}
		}
		// Now we start dealing with MySQL and PostgreSQL.
		// tinyint(1) is a specialty in MySQL for BOOL/BOOLEAN, getting it done will ease our job.
		// The boolean type that is equivalent to TINYINT(1) in MySQL and boolean in PostgreSQL.
		else if($column_type == 'tinyint(1)' || $column_type == 'boolean')
		{
			$columninfo['type'] = MERGE_DATATYPE_BOOL;
			$columninfo['min'] = 0;
			$columninfo['max'] = 1;
		}
		// Then, we figure out the other data types for MySQL and PostgreSQL.
		else
		{
			$unsigned = '';
			if(strpos($column_type, 'unsigned') !== false)
			{
				$unsigned = '_UNSIGNED';
				$column_type = trim(str_replace('unsigned', '', $column_type));
			}

			if(preg_match('#([^()]*)?((?:\(([0-9,]*)\))|)#', $column_type, $column_type_matches) !== false)
			{
				$column_type = trim($column_type_matches[1]);

				$column_type_arg = null;
				$column_type_arg_1 = null;
				$column_type_arg_2 = null;

				// We got a parenthesised argument.
				if(!empty($column_type_matches[2]))
				{
					$column_type_arg = trim($column_type_matches[3]);

					if(strpos($column_type_arg, ',') !== false)
					{
						list($column_type_arg_1, $column_type_arg_2) = explode(',', $column_type_arg, 2);
						$column_type_arg_1 = (int) trim($column_type_arg_1);
						$column_type_arg_2 = (int) trim($column_type_arg_2);
					}
					else
					{
						$column_type_arg = (int) $column_type_arg;
					}
				}

				// The bit type that is equivalent to BIT in MySQL and bit, bit varying in PostgreSQL.
				if($column_type == 'bit' || $column_type == 'bit varying')
				{
					$columninfo['type'] = MERGE_DATATYPE_BIT;

					if(!is_null($column_type_arg))
					{
						$columninfo['length'] = $column_type_arg;
					}
					else
					{
						if($column_type == 'bit varying' && $db->type == 'pgsql')
						{
							$columninfo['length'] = -1;
						}
						else
						{
							$columninfo['length'] = 1;
						}
					}
				}
				// The integer type that can be TINYINT, SMALLINT, MEDIUMINT, INT, BIGINT in MySQL and smallint, integer, bigint in PostgreSQL.
				else if(strpos($column_type, 'int') !== false)
				{
					$columninfo['type'] = MERGE_DATATYPE_INT;

					switch($column_type)
					{
						case 'tinyint':
							// TINYINT in MySQL.
							$int_type_min = 'MERGE_DATATYPE_INT_TINYINT_MIN';
							$int_type_max = 'MERGE_DATATYPE_INT_TINYINT_MAX';
							break;
						case 'smallint':
							// SMALLINT in MySQL and smallint in PostgreSQL.
							$int_type_min = 'MERGE_DATATYPE_INT_SMALLINT_MIN';
							$int_type_max = 'MERGE_DATATYPE_INT_SMALLINT_MAX';
							break;
						case 'mediumint':
							// MEDIUMINT in MySQL.
							$int_type_min = 'MERGE_DATATYPE_INT_MEDIUMINT_MIN';
							$int_type_max = 'MERGE_DATATYPE_INT_MEDIUMINT_MAX';
							break;
						case 'bigint':
							// BIGINT in MySQL and bigint in PostgreSQL.
							$int_type_min = 'MERGE_DATATYPE_INT_BIGINT_MIN';
							$int_type_max = 'MERGE_DATATYPE_INT_BIGINT_MAX';
							break;
						case 'int':
						case 'integer':
						default:
							// INT in MySQL and integer in PostgreSQL.
							$int_type_min = 'MERGE_DATATYPE_INT_INTEGER_MIN';
							$int_type_max = 'MERGE_DATATYPE_INT_INTEGER_MAX';
					}

					$columninfo['min'] = constant($int_type_min.$unsigned);
					$columninfo['max'] = constant($int_type_max.$unsigned);
				}
				// The fixed-point type that can be DECIMAL in MySQL and decimal/numeric in PostgreSQL.
				else if($column_type == 'decimal' || $column_type == 'numeric')
				{
					$columninfo['type'] = MERGE_DATATYPE_FIXED;

					if(!is_null($column_type_arg))
					{
						$columninfo['length'] = $column_type_arg_1;
						$columninfo['scale'] = $column_type_arg_2;
					}
					else
					{
						$columninfo['length'] = -1;
						$columninfo['scale'] = -1;
					}
				}
				// The single precision float-point type that can be FLOAT in MySQL and real in PostgreSQL.
				else if($column_type == 'float' || $column_type == 'real')
				{
					$columninfo['type'] = MERGE_DATATYPE_FLT;
				}
				// The double precision float-point type that can be DOUBLE in MySQL and double precision in PostgreSQL.
				else if($column_type == 'double' || $column_type == 'double precision')
				{
					$columninfo['type'] = MERGE_DATATYPE_DBL;
				}
				// The character/string type that can be CHAR, VARCHAR, TINYTEXT, TEXT, MEDIUMTEXT, LONGTEXT in MySQL and character, char, character varying, varchar, text in PostgreSQL.
				else if(strpos($column_type, 'char') !== false || strpos($column_type, 'text') !== false)
				{
					$columninfo['type'] = MERGE_DATATYPE_CHAR;

					// If a designated character length is given.
					if(!is_null($column_type_arg))
					{
						$columninfo['length'] = $column_type_arg;
						$columninfo['length_type'] = MERGE_DATATYPE_CHAR_LENGTHTYPE_CHAR;
					}
					// Only MySQL has various TEXT types.
					else if(strpos($column_type, 'text') !== false && ($db->type == 'mysql' || $db->type == 'mysqli'))
					{
						switch($column_type)
						{
							case 'tinytext':
								$char_type_length = MERGE_DATATYPE_CHAR_TINYTEXT_LENGTH;
								break;
							case 'mediumtext':
								$char_type_length = MERGE_DATATYPE_CHAR_MEDIUMTEXT_LENGTH;
								break;
							case 'longtext':
								$char_type_length = MERGE_DATATYPE_CHAR_LONGTEXT_LENGTH;
								break;
							case 'text':
							default:
								$char_type_length = MERGE_DATATYPE_CHAR_TEXT_LENGTH;
						}

						$columninfo['length'] = $char_type_length;
						$columninfo['length_type'] = MERGE_DATATYPE_CHAR_LENGTHTYPE_BYTE;
					}
					// On PostgreSQL without a specific character length argument, char type will get ~1GB storage.
					else if($db->type == 'pgsql')
					{
						$columninfo['length'] = MERGE_DATATYPE_CHAR_TEXT_LENGTH_PGSQL;
						$columninfo['length_type'] = MERGE_DATATYPE_CHAR_LENGTHTYPE_BYTE;
					}
					// To be safe.
					else
					{
						$columninfo['length'] = 0;
						$columninfo['length_type'] = MERGE_DATATYPE_CHAR_LENGTHTYPE_CHAR;
					}
				}
				// The binary type that can be BINARY, VARBINARY, TINYBLOB, BLOB, MEDIUMBLOB, LONGBLOB in MySQL and bytea in PostgreSQL.
				else if(strpos($column_type, 'binary') !== false || strpos($column_type, 'blob') !== false || $column_type == 'bytea')
				{
					$columninfo['type'] = MERGE_DATATYPE_BIN;

					// If a designated character length is given.
					if(!is_null($column_type_arg))
					{
						$columninfo['length'] = $column_type_arg;
					}
					// Only MySQL has various BLOB types.
					else if(strpos($column_type, 'blob') !== false && ($db->type == 'mysql' || $db->type == 'mysqli'))
					{
						switch($column_type)
						{
							case 'tinyblob':
								$bin_type_length = MERGE_DATATYPE_BIN_TINYBLOB_LENGTH;
								break;
							case 'mediumblob':
								$bin_type_length = MERGE_DATATYPE_BIN_MEDIUMBLOB_LENGTH;
								break;
							case 'longblob':
								$bin_type_length = MERGE_DATATYPE_BIN_LONGBLOB_LENGTH;
								break;
							case 'blob':
							default:
								$bin_type_length = MERGE_DATATYPE_BIN_BLOB_LENGTH;
						}

						$columninfo['length'] = $bin_type_length;
					}
					// On PostgreSQL without a specific character length argument.
					else if($db->type == 'pgsql')
					{
						// bytea type will get ~1GB storage but the actual size may be subject to host hardware limit.
						$columninfo['length'] = MERGE_DATATYPE_BIN_BLOB_LENGTH_PGSQL;
					}
					// To be safe.
					else
					{
						$columninfo['length'] = 0;
					}
				}
			}
		}

		$table_columninfo[$column['Field']] = $columninfo;
	}

	if($cache)
	{
		$import_session['column_length'][$table] = $table_columninfo;
	}

	return $table_columninfo;
}
