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
		$text = preg_replace('#<!\-\- (.*?) \-\-><a(.*?)href="(.*?)">(.*?)<\/a><!\-\- \1 \-\->#', '[url=\3]\4[/url]', $text);
		$text = preg_replace('#<!\-\- ia(.*?) \-\->(.*?)<!\-\- ia\1 \-\->\[\/attachment\]#', '', $text);
		$text = preg_replace('/\[color=#(.)(.)(.)\]/', '[color=#\1\1\2\2\3\3]', $text);
		return $text;
	}

	function convert_title($text)
	{
		$text = utf8_unhtmlentities($text);

		return $text;
	}
}
?>