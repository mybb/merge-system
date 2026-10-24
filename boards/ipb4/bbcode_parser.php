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
	 *
	 * @param string $text post message
	 * @return string post message
	 */
	function convert($text)
	{
		// Strip IPB4 attachment links BEFORE HTML→BBcode conversion.
		// Attachments are imported separately; these inline links would otherwise
		// be converted to broken URL tags in the post content.
		// Non-image attachments: <a class="ipsAttachLink" href="...">filename</a>
		$text = preg_replace('#<a\s+class="ipsAttachLink[^"]*"\s+href="[^"]*"[^>]*>[^<]*</a>#si', '', $text);
		// Image attachments: unwrap the <a> but keep the <img> for conversion
		$text = preg_replace('#<a\s+[^>]*class="[^"]*ipsAttachLink_image[^"]*"[^>]*>(<img[^>]*>)</a>#si', '$1', $text);

		$text = preg_replace('# data-ipb=\'(.*?)\'#si', "", $text);
		$text = preg_replace('# rel="(.*?)"#si', "", $text);
		$text = preg_replace('#line-height:(.*?)px;#si', "", $text);

		$text = parent::convert($text);

		// This is how ipb saves code blocks...
		$text = preg_replace('#<pre([\s]+)class=".*?ipsCode.*?">(.*?)</pre>#si', "[code]$2[/code]\n", $text);
		
		return $text;
	}
}

