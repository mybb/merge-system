<?php
/**
* MyBB 1.8 Merge System
* Copyright 2014 MyBB Group, All Rights Reserved
*
* Website: http://www.mybb.com
* License: http://www.mybb.com/download/merge-system/license/
*/

if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class BBCode_Parser_Plain {
	// We handle some special codes already here eg [s] and [del] are the same for mybb
	function convert($text)
	{
		// IMG tag with alt attribute - remove the attribute
		$text = preg_replace("#\[img=(.*?)\](.*?)\[/img\]#i", "[img]$2[/img]", $text);
		// Change [del] to [s]
		$text = preg_replace("#\[del\](.*?)\[/del\]#i", "[s]$1[/s]", $text);
		// Change [em] to [i]
		$text = preg_replace("#\[em\](.*?)\[/em\]#i", "[i]$1[/i]", $text);
		// Same save list items as "[*]Item[/*]" so we're removing the closing one
		$text = preg_replace("#\[\*\](.*?)\[/\*\]#i", "[*]$1", $text);
		// Wrong align codes
		$text = str_ireplace(array("[center]", "[/center]", "[left]", "[/left]", "[right]", "[/right]"), array("[align=center]", "[/align]", "[align=left]", "[/align]", "[align=right]", "[/align]"), $text);
		// "[url='http://community.mybb.com']" is used by some boards...
		$text = preg_replace("#\[url='(.*?)'\](.*?)\[/url\]#i", "[url=$1]$2[/url]", $text);

		return $text;
	}

	// Normally not needed, but some boards may call it so it's still here
	function convert_title($text)
	{
		return $text;
	}
}