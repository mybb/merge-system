<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2019 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class BBCode_Parser extends BBCode_Parser_Plain
{
	// This contains the attachment bbcode which is handled as special code as the id needs to be changed too
	// Xenforo 2 has different in-post attachment forms.
	var $attachment = "\[attach.*?\]([0-9]+)\[/attach\]";

	function convert($text)
	{
		// Attachment codes have an optional parameters which we need to remove
		$text = preg_replace("#\[attach.*?\]([0-9]+)\[/attach\]#i", "[attach]$1[/attach]", $text);

		// Try to handle most of Xenforo 2's bbcodes. 
		$text = $this->convert_bbcode($text);
		$text = $this->convert_bbcode_callback($text);

		$text = parent::convert($text);

		$text = preg_replace("#\[html\](.*?)\[/html\]#si", "[php]$1[/php]", $text);

		// Seems inline code is not supported by MyBB 1.8, better leave it untouched.
		//$text = preg_replace("#\[icode\](.*?)\[/icode\]#i", "[code]$1[/code]", $text);

		return $text;
	}

	function convert_bbcode($text)
	{
		$convert_standards = array(
			// xf 2.1, [URL unfurl="true"]...[/URL]
			array(
				'find' => "#\[url\s+(.*?)\](.*?)\[/url\]#i",
				'replacement' => "[url]$2[/url]",
			),
		);

		foreach($convert_standards as $convert)
		{
			$text = preg_replace($convert['find'], $convert['replacement'], $text);
		}

		$convert_nestables = array(
		);

		foreach($convert_nestables as $convert)
		{
			while(preg_match($convert['find'], $text))
			{
				$text = preg_replace($convert['find'], $convert['replacement'], $text);
			}
			
		}

		return $text;
	}

	function convert_bbcode_callback($text)
	{
		$convert_standards = array(
			// [MEDIA=abc]xyz[/MEDIA]
			array(
				'find' => "#\[media=([\w,]+)\]\s*([^\[\<\r\n]+?)\s*\[\/media\]#i",
				'callback' => "handle_media",
			),
		);

		foreach($convert_standards as $convert)
		{
			$text = preg_replace_callback($convert['find'], array($this, $convert['callback']), $text);
		}

		$convert_nestables = array(
			// [COLOR=rgb(r, g, b)]...[/COLOR]
			array(
				'find' => "#\[color=(rgb\([\d\s,]+?\))\](.*?)\[/color\]#si",
				'callback' => 'handle_color_rgb',
			),
			// [size=X]...[/size]
			array(
				'find' => "#\[size=(\d{1,2}?)\](.*?)\[/size\]#i",
				'callback' => 'handle_size_value',
			),
		);

		foreach($convert_nestables as $convert)
		{
			while(preg_match($convert['find'], $text))
			{
				$text = preg_replace_callback($convert['find'], array($this, $convert['callback']), $text);
			}
		}

		return $text;
	}

	/**
	 * Callback for media handling.
	 *
	 * @param array $matches
	 *
	 * @return string
	 */
	function handle_media($matches)
	{
		// array( $provider_xf => array( 'provider_mybb' => $provider_mybb, 'provider_url' => $provider_url ), );
		// TODO: Only handle videos supported by MyBB 1.8? Or return formal URL?
		$supported_media = array(
			"youtube" => array(
				"provider_mybb" => "youtube",
				"provider_url" => "https://www.youtube.com/watch?v=$1",
			),
			"dailymotion" => array(
				"provider_mybb" => "dailymotion",
				"provider_url" => "https://www.dailymotion.com/video/$1",
			),
			"liveleak" => array(
				"provider_mybb" => "liveleak",
				"provider_url" => "https://www.liveleak.com/view?t=$1",
			),
			"facebook" => array(
				"provider_mybb" => "facebook",
				"provider_url" => "http://www.facebook.com/video/video.php?v=$1",
			),
			"metacafe" => array(
				"provider_mybb" => "metacafe",
				"provider_url" => "https://www.metacafe.com/watch/$1/",
			),
			"vimeo" => array(
				"provider_mybb" => "vimeo",
				"provider_url" => "https://vimeo.com/$1",
			),
			"twitch" => array(
				"provider_mybb" => "twitch",
				"provider_url" => "https://www.twitch.tv/$1",
			),
		);

		if(array_key_exists($matches[1], $supported_media))
		{
			if(!empty($supported_media[$matches[1]]['provider_mybb']))
			{
				$provider_mybb = $supported_media[$matches[1]]['provider_mybb'];
				$provider_url = str_replace("$1", $matches[2], $supported_media[$matches[1]]['provider_url']);
				return "[video={$provider_mybb}]{$provider_url}[/video]";
			}
		}
		return $matches[0];	// TODO: just return $matches[0]?
	}

	/**
	 * Callback for color using a RGB() value.
	 *
	 * @param array $matches
	 *
	 * @return string
	 */
	function handle_color_rgb($matches)
	{
		$color = '#';
		$rgb_colors = array_filter(explode(",", substr(trim($matches[1]), 4, -1)));
		for($i = 0; $i < 3; $i++)
		{
			$rgb_color = dechex(abs(intval(trim($rgb_colors[$i]))));
			while(strlen($rgb_color) < 2)
			{
				$rgb_color = '0' . $rgb_color;
			}
			$color .= $rgb_color;
		}
		return "[color={$color}]{$matches[2]}[/color]";
	}

	/**
	 * Callback for size attribute without a unit.
	 *
	 * @param array $matches
	 *
	 * @return string
	 */
	function handle_size_value($matches)
	{
		$size = (int)$matches[1];
		switch($size)
		{
			case 1:
				$size = 'x-small';
				break;
			case 2:
				$size = 'small';
				break;
			case 3:
				$size = 'medium';
				break;
			case 4:
				$size = 'large';
				break;
			case 5:
				$size = 'x-large';
				break;
			case 6:
				$size = 'xx-large';
				break;
			default:
				$size = 'medium';
				break;
		}
		return "[size={$size}]{$matches[2]}[/size]";
	}
}
