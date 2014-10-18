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

class BBCode_Parser extends BBCode_Parser_HTML {

	/**
	 * Unconvert the HTML in posts, back to BBCode
	 * @param ref parser object
	 * @param string post message
	 * @return string post message
	 */
	function convert($text)
	{
		$text = preg_replace('# data-ipb=\'(.*?)\'#si', "", $text);
		$text = preg_replace('# rel="(.*?)"#si', "", $text);

		$text = parent::convert($text);

		// This is how ipb saves code blocks...
		$text = preg_replace('#<pre([\s]+)class=".*?ipsCode.*?">(.*?)</pre>#si', "[code]$2[/code]\n", $text);
		
		return $text;
	}
}
?>