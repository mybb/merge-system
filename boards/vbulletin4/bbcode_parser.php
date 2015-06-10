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

	// This contains the attachment bbcode which is handled as special code as the id needs to be changed too
	var $attachment = "\[attach\]([0-9]+)\[/attach\]";

	function convert($text)
	{
		$find = array(
			'#\[COLOR\="([a-zA-Z]*|\#?[\da-fA-F]{3}|\#?[\da-fA-F]{6})"\](.*?)\[/COLOR\]#i',
			'#\[QUOTE\=(.*?);([0-9]+?)\]#i',
			'#\[url\="(.*?)"](.*?)\[/url\]#i',
			'#\[email\="(.*?)"](.*?)\[/email\]#i',
		);

		$replace = array(
			'[color=$1]$2[/color]',
			"[quote=$1]",
			'[url=$1]$2[/url]',
			'[email=$1]$2[/email]',
		);

		$text = preg_replace($find, $replace, $text);

		// Closing line tags
		$text = str_ireplace("[/hr]", "", $text);

		return parent::convert($text);
	}
}
