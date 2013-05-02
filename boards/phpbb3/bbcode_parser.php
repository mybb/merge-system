<?php
/**
 * MyBB 1.6
 * Copyright © 2009 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
  * License: http://www.mybb.com/about/license
 *
 * $Id: bbcode_parser.php 4394 2010-12-14 14:38:21Z ralgith $
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class BBCode_Parser {

	/**
	 * Converts messages containing phpBB code to MyBB BBcode
	 *
	 * @param string the text to convert
	 * @param int user id of the text
	 * @return string the converted text
	 */
	function convert($text, $uid=0)
	{
		$text = str_replace(array(':'.$uid, '[/*:m]', '[/list:o]', '[/list:u]'), array('', '', '[/list]', '[/list]'), utf8_unhtmlentities($text));
		$text = preg_replace('#<!\-\- s(.*?) \-\-><img src="\{SMILIES_PATH\}\/.*? \/><!\-\- s\1 \-\->#', '\1', $text);
		$text = preg_replace('#<!\-\- (.*?) \-\-><a(.*?)href="(.*?)" \/>(.*?)<\/a><!\-\- \1 \-\->#', '\2', $text);
		$text = preg_replace('#<!\-\- (.*?) \-\-><a(.*?)href="(.*?)" \/><!\-\- \1 \-\->#', '\2', $text);
		$text = preg_replace('#<!\-\- ia(.*?) \-\->(.*?)<!\-\- ia\1 \-\->\[\/attachment\]#', '', $text);
		
		return $text;
	}
	
	function convert_title($text)
	{
		$text = utf8_unhtmlentities($text);
		
		return $text;
	}
}
?>