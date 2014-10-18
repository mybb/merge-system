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

class BBCode_Parser extends BBCode_Parser_Plain{

	/**
	 * Converts punBB BBCode to MyBB MyCode
	 *
	 * @param string Text to convert
	 * @return string converted text
	 */
	 function convert($text)
	 {
	 	// First: do our usual things
		$text = parent::convert($text);

	 	// PunBB saves normal lists as "[list=*]" so we need to remove that
		$text = preg_replace("#\[list=\*\]#i", "[list]", $text);

		return $text;
	 }
}
?>