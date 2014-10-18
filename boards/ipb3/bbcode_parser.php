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

		$text = parent::convert($text);

		// It would be too mainstream to simply use the different html lists - IPB uses classes instead...
		$text = preg_replace('#<ul([\s]+)class="bbc">(.*?)</ul>#si', "[list]$2[/list]\n", $text);
		$text = preg_replace('#<ul([\s]+)class="bbc bbcol decimal">(.*?)</ul>#si', "[list=1]$2[/list]\n", $text);

		// This is how ipb saves code blocks...
		$text = preg_replace('#<pre([\s]+)class="_prettyXprint (.*?)">(.*?)</pre>#si', "[code]$3[/code]\n", $text);

		// Special IPB Codes
		$text = preg_replace('#\[twitter\](.*?)\[/twitter\]#si', "[url=https://twitter.com/$1]$1[/url]\n", $text);
		$text = preg_replace_callback("#\[topic='([0-9]+)'\](.*?)\[/topic\]#i", array($this, "topic_callback"), $text);
		$text = preg_replace_callback("#\[post='([0-9]+)'\](.*?)\[/post\]#i", array($this, "post_callback"), $text);
		
		return $text;
	}

	function topic_callback($matches)
	{
		global $mybb, $module;

		$id = $module->get_import->tid($matches[1]);

		if(count($matches) == 3)
		{
			return "[url={$mybb->settings['bburl']}/showthread.php?tid={$id}]{$matches[2]}[/url]";
		}
		return "[url]{$mybb->settings['bburl']}/showthread.php?tid={$id}[/url]";
	}
	function post_callback($matches)
	{
		global $mybb, $module;

		$id = $module->get_import->pid($matches[1]);

		if(count($matches) == 3)
		{
			return "[url={$mybb->settings['bburl']}/showthread.php?pid={$id}]{$matches[2]}[/url]";
		}
		return "[url]{$mybb->settings['bburl']}/showthread.php?pid={$id}[/url]";
	}
}
?>