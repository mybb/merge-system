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

class BBCode_Parser extends BBCode_Parser_Plain {

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

		parent::convert($text);

		return $text;
	}

	function convert_title($text)
	{
		$text = utf8_unhtmlentities($text);

		return $text;
	}
}
?>