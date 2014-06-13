<?php
/**
 * MyBB 1.6
 * Copyright 2009 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class BBCode_Parser {

	function convert($message)
	{
		$message = str_ireplace(array('[right]', '[/right]', '[left]', '[/left]', '[center]', '[/center]', "<br />", '[ftp', '[/ftp]', '<!-- m', '<!-- s', '-->'), array('[align=right]', '[/align]', '[align=left]', '[/align]', '[align=center]', '[/align]', "\n", '[url', '[/url]', '', '', ''), $message);
		$message = preg_replace("#\[size=([0-9\+\-]+?)p[tx]\](.*?)\[/size\]#si", "[size=$1]$2[/size]", $message);
		$message = preg_replace("#\[li\](.*?)\[/li\]#si", "[*]$1", $message);
		$message = preg_replace("#\[img width=([0-9\+\-]+?) height=([0-9\+\-]+?)\]#si", "[img=$1x$2]", $message);
		$message = preg_replace("#\[quote(.*?)\](.*?)\[\/quote\]#esi", "\$this->mycode_parse_post_quotes('$2', '$1')", $message);
		return $message;
	}

	/**
	* Parses SMF quotes with author, post id and/or dateline.
	*
	* @param string The message to be parsed
	* @param string The information to be parsed
	* @return string The parsed message.
	*/
	function mycode_parse_post_quotes($message, $info)
	{
		require_once MERGE_ROOT.'resources/class_cache_handler.php';
		$this->get_import = new Cache_Handler();

		$info = trim($info);

		preg_match("#author=(.*?)=#i", $info, $match);
		if($match[1])
		{
			$username = $match[1];
		}

		preg_match("#link=topic=([0-9]+).msg?([0-9]+)\#msg([0-9]+)?#i", $info, $match);
		if($match[1])
		{
			$pid = $this->get_import->pid($match[1]);
		}

		preg_match("#date=?([0-9]+)#i", $info, $match);
		if($match[1])
		{
			$dateline = $match[1];
		}

		// Build the return quote, in case not all fields are present.
		$retval = '[quote';
		if(isset($username))
		{
			$retval .= "='{$username}'";
		}
		if(isset($pid))
		{
			$retval .= " pid='{$pid}'";
		}
		if(isset($dateline))
		{
			$retval .= " dateline='{$dateline}'";
		}
		$retval .= "]{$message}[/quote]";

		return $retval;
	}
}
?>