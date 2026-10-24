<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 *
 * Discourse BBCode Parser
 * Discourse stores posts as "cooked" HTML (rendered from Markdown).
 * We use the HTML→BBCode parent parser and add Discourse-specific
 * HTML cleanup for elements like aside.quote, div.poll, lightbox images, etc.
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class BBCode_Parser extends BBCode_Parser_HTML {

	function convert($text)
	{
		// Strip lazyYT / lazy-video embedded video wrappers
		$text = preg_replace('#<div class="lazy.*?".*?>.*?</div>#si', '', $text);

		// Strip Discourse poll divs (they won't convert to BBcode)
		$text = preg_replace('#<div class="poll".*?>.*?</div>#si', '', $text);

		// Strip Discourse lightbox wrapper (keep the img inside)
		$text = preg_replace('#<a class="lightbox".*?>(.*?)</a>#si', '$1', $text);

		// Strip data-discourse attributes
		$text = preg_replace('# data-\w+=".*?"#si', '', $text);

		// Convert Discourse quote blocks to MyBB quote format
		// Discourse: <aside class="quote" data-username="..." data-post="..."><div class="title">...</div><blockquote>...</blockquote></aside>
		$text = preg_replace_callback('#<aside class="quote.*?>(.*?)</aside>#si', function($matches) {
			$content = $matches[1];
			// Extract title/attribution
			$content = preg_replace('#<div class="title">(.*?)</div>#si', '', $content);
			// Extract blockquote content
			if(preg_match('#<blockquote>(.*?)</blockquote>#si', $content, $qm))
			{
				return "[quote]".trim($qm[1])."[/quote]\n";
			}
			return '[quote]'.$content.'[/quote]';
		}, $text);

		// Parent HTML→BBcode conversion
		$text = parent::convert($text);

		return $text;
	}
}
