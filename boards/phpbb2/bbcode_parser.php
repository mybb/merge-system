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

	/**
	 * Converts messages containing phpBB code to MyBB BBcode
	 *
	 * @param string the text to convert
	 * @param int user id of the text
	 * @return string the converted text
	 */
	function convert($text, $uid=0)
	{
		$text = str_replace(':'.$uid, '', utf8_unhtmlentities($text));
		$text = str_replace("[/list:u]", "[/list]", $text);
		$text = str_replace("[/list:o]", "[/list]", $text);
		$text = str_replace("[code:1]", "[code]", $text);
		$text = str_replace("[/code:1]", "[/code]", $text);

		return $text;
	}
}
?>