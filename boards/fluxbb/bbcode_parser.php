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
	 	// BBPress uses a closing tag for list items which we need to remove
		$text = preg_replace("#\[list=\*\]#i", "[list]", $text);
		$text = preg_replace("#\[\*\](.*?)\[/\*\]#i", "[*]$1", $text);
	 	// Img tags can have an alt attribute: [img=alt]link[/img]
		$text = preg_replace("#\[img=(.*?)\](.*?)\[/img\]#i", "[img]$2[/img]", $text);
		
		return $text;
	 }
}
?>