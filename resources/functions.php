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

	// Stats
	if(!empty($board->old_db->query_count))
	{
		$import_session['olddb_query_count'] += $board->old_db->query_count;
	}
	$import_session['newdb_query_count'] += $db->query_count;
	$import_session['total_query_time'] += $db->query_time;

	$import_session['completed'] = array_unique($import_session['completed']);

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

		$debug->log->datatrace('$debug_import_session', $debug_import_session);
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
 * @param int Integer to be converted
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
 * @param int Integer to be converted
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
 * @param array Errors
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
 * @param boolean Show text progress
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

	if($text == true)
	{
		$output->update_progress_bar(0, $lang->sprintf($lang->removing_table, TABLE_PREFIX."post_trackers"));
	}
	$db->drop_table("post_trackers");

	if($text == true)
	{
		$output->update_progress_bar(0, $lang->sprintf($lang->removing_table, TABLE_PREFIX."privatemessage_trackers"));
	}
	$db->drop_table("privatemessage_trackers");

	$drop_list = array(
		"users" => array('import_uid', 'import_usergroup', 'import_additionalgroups', 'import_displaygroup'),
		"forums" => array('import_fid', 'import_pid'),
		"threads" => array('import_tid', 'import_uid', 'import_poll', 'import_firstpost'),
		"polls" => array('import_pid', 'import_tid'),
		"usergroups" => array('import_gid'),
		"events" => array('import_eid'),
		"attachments" => array('import_aid'),
	);

	$increment = 200/(count($drop_list, COUNT_RECURSIVE)-count($drop_list));
	$progress = 0;
	foreach($drop_list as $table => $columns)
	{
		$columns_list = implode(', ', $columns);
		$comma = "";
		$columns_sql = "";
		foreach($columns as $column)
		{
			if($db->field_exists($column, $table))
			{
				$columns_sql .= "{$comma} DROP ".$column;
				$comma = ",";
			}
		}

		if($text == true)
		{
			$output->update_progress_bar($progress, $lang->sprintf($lang->removing_columns, $columns_list, TABLE_PREFIX.$table));
			$progress += $increment;
		}

		$db->write_query("ALTER TABLE ".TABLE_PREFIX.$table."{$columns_sql}");
	}

	$db->delete_query("datacache", "title='import_cache'");
}

/**
 * Create the temporary importing data fields we use to keep track of, for example, vB's imported user id, etc.
 *
 * @param boolean Show text progress
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

	if($text == true)
	{
		$output->update_progress_bar(0, $lang->sprintf($lang->creating_table, TABLE_PREFIX."trackers"));
	}

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."trackers (
	  type varchar(20) NOT NULL default '',
	  count int NOT NULL default '0',
	  PRIMARY KEY (type),
	  KEY count (count)
	) ENGINE=MyISAM;");

	if($text == true)
	{
		$output->update_progress_bar(0, $lang->sprintf($lang->creating_table, TABLE_PREFIX."post_trackers"));
	}

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."post_trackers (
	  pid int NOT NULL default '0',
	  import_pid int NOT NULL default '0',
	  import_uid int NOT NULL default '0',
	  PRIMARY KEY (pid),
	  KEY import_pid (import_pid),
	  KEY import_uid (import_uid)
	) ENGINE=MyISAM;");

	if($text == true)
	{
		$output->update_progress_bar(0, $lang->sprintf($lang->creating_table, TABLE_PREFIX."privatemessage_trackers"));
	}

	$db->write_query("CREATE TABLE ".TABLE_PREFIX."privatemessage_trackers (
	  pmid int NOT NULL default '0',
	  import_pmid int NOT NULL default '0',
	  PRIMARY KEY (pmid),
	  KEY import_pmid (import_pmid)
	) ENGINE=MyISAM;");

	$add_list = array(
		"int" => array(
			"users" => array('import_uid', 'import_usergroup', 'import_displaygroup'),
			"forums" => array('import_fid', 'import_pid'),
			"threads" => array('import_tid', 'import_uid', 'import_poll', 'import_firstpost'),
			"polls" => array('import_pid', 'import_tid'),
			"usergroups" => array('import_gid'),
			"events" => array('import_eid'),
			"attachments" => array('import_aid'),
		),
		"text" => array(
			"users" => array('passwordconvert', 'passwordconverttype', 'passwordconvertsalt', 'import_additionalgroups'),
		),
	);

	foreach($add_list as $array)
	{
		$increment += (count($array, COUNT_RECURSIVE)-count($array));
	}

	$increment = 200/$increment;
	$progress = 0;
	foreach($add_list['int'] as $table => $columns)
	{
		$columns_list = implode(', ', $columns);
		$comma = "";
		$columns_sql = "";
		foreach($columns as $column)
		{
			if(!$db->field_exists($column, $table))
			{
				$columns_sql .= "{$comma} ADD ".$column." int NOT NULL default '0'";
				$comma = ",";
			}
		}

		if($text == true)
		{
			$output->update_progress_bar($progress, $lang->sprintf($lang->creating_columns, "int", $columns_list, TABLE_PREFIX.$table));
			$progress += $increment;
		}

		$db->write_query("ALTER TABLE ".TABLE_PREFIX.$table."{$columns_sql}");

		if($db->type == "mysql" || $db->type == "mysqli")
		{
			foreach($columns as $column)
			{
				$db->write_query("ALTER TABLE ".TABLE_PREFIX.$table." ADD INDEX ( `{$column}` )");
			}
		}
	}

	foreach($add_list['text'] as $table => $columns)
	{
		$columns_list = implode(', ', $columns);
		$comma = "";
		$columns_sql = "";
		foreach($columns as $column)
		{
			if(!$db->field_exists($column, $table))
			{
				$columns_sql .= "{$comma} ADD ".$column." text";
				$comma = ",";
			}
		}

		$db->write_query("ALTER TABLE ".TABLE_PREFIX.$table."{$columns_sql}");

		if($text == true)
		{
			$output->update_progress_bar($progress, $lang->sprintf($lang->creating_columns, "text", $columns_list, TABLE_PREFIX.$table));
			$progress += $increment;
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
 * @param string The text to convert
 * @param string The old table (e.x. vB's user table)
 * @param string The new table (e.x. MyBB's user table)
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
 * @param string The MySQL encoding
 * @param The iconv encoding
 */
function fetch_iconv_encoding($mysql_encoding)
{
    $mysql_encoding = explode("_", $mysql_encoding);
    switch($mysql_encoding[0])
    {
		case "utf8":
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
 * @param int The forum ID
 * @param string Optional separator - defaults to comma for CSV list
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
 * @param int The forum ID
 * @param string Optional separator - defaults to comma for CSV list
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
 * Salts a password based on a supplied salt.
 *
 * @param string The md5()'ed password.
 * @param string The salt.
 * @return string The password hash.
 */
function salt_password($password, $salt)
{
	return md5(md5($salt).$password);
}

/**
 * Generates a random salt
 *
 * @return string The salt.
 */
function generate_salt()
{
	return random_str(8);
}

/**
 * Generates a 50 character random login key.
 *
 * @return string The login key.
 */
function generate_loginkey()
{
	return random_str(50);
}

/**
 * Checks for the existance of a file via url (via http status code)
 *
 * @param string The link to the url
 * @return boolean Whether or not the url exists
 */
function check_url_exists($url)
{
	if(!$url)
	{
		return false;
	}

	$buffer = '';

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

	$headers = get_headers("$url_parsed[scheme]://$url_parsed[host]:$url_parsed[port]{$url_parsed['path']}");

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
 * @param string The URL of the remote file
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

	// Use this method if we have a relative or absolute path as our url
	if(my_substr($url, 0, 1) == '.' || my_substr($url, 0, 1) == '/' || my_substr($url, 1, 2) == ':\\')
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
	 * @param string The encoded string of html special characters
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
 * @param string The string to un-htmlentitize.
 * @return int The un-htmlentitied' string.
 */
function utf8_unhtmlentities($string)
{
	// Replace numeric entities
	$string = preg_replace('~&#x([0-9a-f]+);~ei', 'unichr(hexdec("\\1"))', $string);
	$string = preg_replace('~&#([0-9]+);~e', 'unichr("\\1")', $string);

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
	 * @param string The ascii to characterize.
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
 * @param string The string to check
 * @param string the encoding to check against
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

	$limit = preg_match("#^([0-9]+)\s?([kmg])b?$#i", trim(my_strtolower($memory_limit)), $matches);
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
		$comma = ", ";
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
	$time = new DateTime('now', new DateTimeZone($zone));
	$off = $time->format('P');
	
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
?>