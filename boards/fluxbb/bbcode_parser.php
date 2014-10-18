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

class BBCode_Parser extends BBCode_Parser_Plain {

	/**
	 * Converts fluxBB BBCode to MyBB MyCode
	 *
	 * @param string Text to convert
	 * @return string converted text
	 */
	 function convert($text)
	 {
	 	// First: do our usual things
		$text = parent::convert($text);

	 	// FluxBB saves normal lists as "[list=*]" so we need to remove that
		$text = preg_replace("#\[list=\*\]#i", "[list]", $text);

		// FluxBB has some special bbcodes which we need to solve:
		// Thread/Topic:
		$text = preg_replace_callback("#\[topic=([0-9]+)\](.*?)\[/topic\]#i", array($this, "topic_callback"), $text);
		$text = preg_replace_callback("#\[topic\]([0-9]+)\[/topic\]#i", array($this, "topic_callback"), $text);
		// Post:
		$text = preg_replace_callback("#\[post=([0-9]+)\](.*?)\[/post\]#i", array($this, "post_callback"), $text);
		$text = preg_replace_callback("#\[post\]([0-9]+)\[/post\]#i", array($this, "post_callback"), $text);
		// Forum:
		$text = preg_replace_callback("#\[forum=([0-9]+)\](.*?)\[/forum\]#i", array($this, "forum_callback"), $text);
		$text = preg_replace_callback("#\[forum\]([0-9]+)\[/forum\]#i", array($this, "forum_callback"), $text);
		// User:
		$text = preg_replace_callback("#\[user=([0-9]+)\](.*?)\[/user\]#i", array($this, "user_callback"), $text);
		$text = preg_replace_callback("#\[user\]([0-9]+)\[/user\]#i", array($this, "user_callback"), $text);

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
	function forum_callback($matches)
	{
		global $mybb, $module;

		$id = $module->get_import->fid($matches[1]);

		if(count($matches) == 3)
		{
			return "[url={$mybb->settings['bburl']}/forumdisplay.php?fid={$id}]{$matches[2]}[/url]";
		}
		return "[url]{$mybb->settings['bburl']}/forumdisplay.php?fid={$id}[/url]";
	}
	function user_callback($matches)
	{
		global $mybb, $module;

		$id = $module->get_import->uid($matches[1]);

		if(count($matches) == 3)
		{
			return "[url={$mybb->settings['bburl']}/member.php?action=profile&uid={$id}]{$matches[2]}[/url]";
		}
		return "[url]{$mybb->settings['bburl']}/member.php?action=profile&uid={$id}[/url]";
	}
}
?>