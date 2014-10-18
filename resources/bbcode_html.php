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

// We extend the plain class to make sure all our functions exist
class BBCode_Parser_HTML extends BBCode_Parser_Plain {
	// We handle some special codes already here eg [s] and [del] are the same for mybb
	function convert($text)
	{
		$text = str_replace(array("<br>", "<br />"), "\n", $text);
		$text = preg_replace('#<em>(.*?)</em>#si', '[i]$1[/i]', $text);
		$text = preg_replace('#<u>(.*?)</u>#si', '[u]$1[/u]', $text);
		$text = preg_replace('#<strong>(.*?)</strong>#si', '[b]$1[/b]', $text);
		$text = preg_replace('#<strike>(.*?)</strike>#si', '[s]$1[/s]', $text);
		$text = preg_replace('#<s>(.*?)</s>#si', '[s]$1[/s]', $text);
		$text = preg_replace('#<blockquote.*?>(.*?)</blockquote>#si', '[quote]$1[/quote]', $text);
		$text = preg_replace('#<code>(.*?)</code>#si', '[code]$1[/code]', $text);
		$text = preg_replace('#<a href="(.*?)".*?>(.*?)</a>#si', '[url=$1]$2[/url]', $text);
		$text = preg_replace('#<a href=\'(.*?)\'.*?>(.*?)</a>#si', '[url=$1]$2[/url]', $text);
		$text = preg_replace('#<del .*?>(.*?)</del>#si', '[s]$1[/s]', $text);
//		$text = preg_replace('#<img src="(.*?)".*? />#si', '[img]$1[/img]', $text);
		$text = preg_replace('#<img src="(.*?)".*?>#si', '[img]$1[/img]', $text);
		$text = preg_replace('#<p style="text-align: ?(left|center|right|justify);?">(.*?)</p>#si', "[align=$1]$2[/align]\n", $text);
		$text = preg_replace('#<div style="text-align: ?(left|center|right|justify);?">(.*?)</div>#si', "[align=$1]$2[/align]\n", $text);
		$text = preg_replace('#<span style="color: ?([a-zA-Z]*|\#?[\da-fA-F]{3}|\#?[\da-fA-F]{6});?">(.*?)</span>#si', "[color=$1]$2[/color]\n", $text);
		$text = preg_replace('#<span style="font-family: ?([a-z0-9 ,\-_\'"]+);?">(.*?)</span>#si', "[font=$1]$2[/font]\n", $text);
		$text = str_replace(array('<p>', '</p>'), array('', "\n"), $text);

		// Size code, we save it a bit different than actual used
		$text = preg_replace('#<span style="font-size: ?(xx-small|x-small|small|medium|large|x-large|xx-large);?">(.*?)</span>#si', "[size=$1]$2[/size]\n", $text);
		$text = preg_replace_callback('#<span style="font-size: ?([0-9\+\-]+?)(px|pt);?">(.*?)</span>#si', array($this, "handle_size"), $text);

		// We all love lists...
		$text = preg_replace('#<ul>(.*?)</ul>#si', "[list]$1[/list]", $text);
		$text = preg_replace('#<ol>(.*?)</ol>#si', "[list=1]$1[/list]", $text);
		$text = preg_replace('#<li>(.*?)</li>#si', "[*]$1", $text);

		return $text;
	}

	function handle_size($matches)
	{
		if($matches[1] > 50)
		{
		    $matches[1] = 50;
		}

		$size = (int)$matches[1]-10;

		return "[size={$size}]{$matches[3]}[/size]";
	}
}