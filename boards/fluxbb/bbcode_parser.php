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
	 * Converts fluxBB BBCode to MyBB MyCode
	 *
	 * @param string Text to convert
	 * @return string converted text
	 */
	 function convert($text)
	 {
	 	$text = preg_replace("#\[center](.*?)\[/center\]#i", "[align=center]$1[/align]", $text);
		$text = preg_replace("#\[large\](.*?)\[/large\]#i", "[size=large]$1[/size]", $text);
		
		return $text;
	 }
}
?>