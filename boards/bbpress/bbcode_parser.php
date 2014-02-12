<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 *
 * $Id$
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class BBCode_Parser {

	/**
	 * Converts messages containing BBcode (html in this case) to MyCode
	 *
	 * @param string the text to convert
	 * @param int user id of the text
	 * @return string the converted text
	 */
	function convert($text, $uid=0)
	{
		$text = preg_replace('#<em>(.*?)</em>#', '[i]$1[/i]', $text);
		$text = preg_replace('#<strong>(.*?)</strong>#', '[b]$1[/b]', $text);
		$text = preg_replace('#<blockquote>(.*?)</blockquote>#', '[quote]$1[/quote]', $text);
		$text = preg_replace('#<code>(.*?)</code>#', '[code]$1[/code]', $text);
		$text = preg_replace('#<a href ="(.*?)".*?>(.*?)</em>#', '[url=$1]$2[/url]', $text);
		$text = str_replace(array('<p>', '</p>'), array('', ''), $text);

		return $text;
	}

	function convert_title($text)
	{
		$text = utf8_unhtmlentities($text);

		return $text;
	}
}
?>