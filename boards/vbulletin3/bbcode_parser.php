<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2011 MyBB Group, All Rights Reserved
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

	function convert($text)
	{
		$find = array(
			'[CENTER]',
			'[/CENTER]',
			'[LEFT]',
			'[/LEFT]',
			'[RIGHT]',
			'[/RIGHT]',
			'[QUOTE]',
			'[/QUOTE]',
			'[B]',
			'[/B]',
			'[PHP]',
			'[/PHP]',
			'[URL',
			'[/URL]',
			'[I]',
			'[/I]',
			'[U]',
			'[/U]',
			'[IMG]',
			'[/IMG]',
			'[/QUOTE]',
		);

		$replace = array(
			'[align=center]',
			'[/align]',
			'[align=left]',
			'[/align]',
			'[align=right]',
			'[/align]',
			'[quote]',
			'[/quote]',
			'[b]',
			'[/b]',
			'[php]',
			'[/php]',
			'[url',
			'[/url]',
			'[i]',
			'[/i]',
			'[u]',
			'[/u]',
			'[img]',
			'[/img]',
			'[/quote]',
		);

		if(function_exists("str_ireplace"))
		{
			$text = str_ireplace($find, $replace, $text);
		}
		else
		{
			$text = str_replace($find, $replace, $text);
		}

		$find = array(
			'#\[COLOR\="(.*?)"\](.*?)\[/COLOR\]#i', // TODO: Test?!
			'#\[COLOR\=(.*?)\](.*?)\[/COLOR\]#i',
			'#\[QUOTE\=(.*?);([0-9]+?)\]#i',
			'#\[url\="(.*?)"](.*?)\[/url\]#i',
			'#\[url\=(.*?)](.*?)\[/url\]#i',
		);

		$replace = array(
			'[color=$1]$2[/color]',
			'[color=$1]$2[/color]',
			"[quote=$1]",
			'[url=$1]$2[/url]',
			'[url=$1]$2[/url]',
		);

		$text = preg_replace($find, $replace, $text);

		return $text;
	}
}
?>