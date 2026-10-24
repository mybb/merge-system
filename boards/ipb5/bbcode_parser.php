<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 *
 * IPS5 BBCode Parser
 * TODO: IPS5 may have changed its HTML output format significantly.
 * Verify that data-ipb attributes, ipsCode classes, and inline styles still match.
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class BBCode_Parser extends BBCode_Parser_HTML {

	/**
	 * Unconvert the HTML in posts, back to BBCode
	 *
	 * @param string $text post message
	 * @return string post message
	 */
	function convert($text)
	{
		// Strip attachment links before HTML→BBcode conversion
		$text = preg_replace('#<a\s+class="ipsAttachLink[^"]*"\s+href="[^"]*"[^>]*>[^<]*</a>#si', '', $text);
		$text = preg_replace('#<a\s+[^>]*class="[^"]*ipsAttachLink_image[^"]*"[^>]*>(<img[^>]*>)</a>#si', '$1', $text);

		// TODO: IPS5 may use different data attributes. Verify these regex patterns.
		$text = preg_replace('# data-ipb=\'(.*?)\'#si', "", $text);
		$text = preg_replace('# rel="(.*?)"#si', "", $text);
		$text = preg_replace('#line-height:(.*?)px;#si', "", $text);

		$text = parent::convert($text);

		// TODO: IPS5 may use different code block markup. Verify ipsCode class.
		$text = preg_replace('#<pre([\s]+)class=".*?ipsCode.*?">(.*?)</pre>#si', "[code]$2[/code]\n", $text);

		return $text;
	}
}
