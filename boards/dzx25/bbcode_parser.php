<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2019 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 */

if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class BBCode_Parser extends BBCode_Parser_HTML {
	
	// This contains the attachment bbcode which is handled as special code as the id needs to be changed too
	var $attachment = "\[(attachimg|attach)\]([0-9]+)\[/\\1\]";
	
	// Discuz! X2.5 really doesn't care about if a bbcode is nested in another one.
	// For a big number of bbcodes, it just replace the opening and ending tags with 
	// corresponding html entities.
	var $dz_code_nestable = array(
			'align',
			'backcolor',
			'color',
			'indent',
			);
	
	var $dz_code_standard = array(
			'img',
			'media',
			'hide',
			);
	
	/**
	 * Discuz! allows nesting for many discuzcode and replies correct parsing with HTML.
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	function convert($text)
	{
		// Convert discuzcode.
		$text = $this->convert_discuzcode($text);
		
		$text = $this->handle_attachments($text);
		
		return $text;
	}
	
	/**
	 * Discuz! allows nesting for many discuzcode and replies correct parsing with HTML.
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	function convert_post($text, $encoding = 'utf-8')
	{
		// Convert discuzcode.
		$text = $this->convert_discuzcode($text, $this->get_encoding($encoding));
		
		$text = $this->handle_attachments($text);
		
		return $text;
	}
	
	/**
	 * Try to convert a user's signature. Discuz! X2.5 stores some HTML in it and old versions use bbcode.
	 * Any user-defined discuzcode will not be covered here.
	 * @param string $text
	 *
	 * @return string
	 */
	function convert_sig($text, $encoding = 'utf-8')
	{
		// Strip unwanted html entities' attributes. Such as <img>'s onload=...
		$text = $this->dz_fix_sightml($text, $this->get_encoding($encoding));
		
		// Convert some of its HTML codes to bbcodes.
		$text = $this->dz_html2bbcode($text);
		
		// Convert discuzcode.
		$text = $this->convert_discuzcode($text, $this->get_encoding($encoding));
		
		return $text;
	}
	
	/**
	 * Try to handle discuz code here.
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	function convert_discuzcode($text, $encoding = 'utf-8')
	{
		// Filter out [code] and [php] tags. Borrowed from MyBB postParser class.
		$code_matches = array();
		// This code is reserved and could break codes
		$text = str_replace("[_mybb_dzx_converter_code_]\n", "[-mybb-dzx-converter-code-]\n", $text);
		// This pattern is copied from discuzcode() in Discuz! X2.5
		preg_match_all("#\s?\[code\](.+?)\[\/code\]\s?#is", $text, $code_matches, PREG_SET_ORDER);
		$text = preg_replace("#\s?\[code\](.+?)\[\/code\]\s?#is", "[_mybb_dzx_converter_code_]\n", $text);
		
		// Convert HTML entities to their characters.
		$text = htmlentities($text);
		
		// Discuzcode allows nested code, and Discuz! depends user browser to make the content appear correct.
		// For MyBB Parser to work, it's best to fix them by removing some nested code.
		// And for converting any disuczcode to MyBB format, which may be netsted, it also needs to be fixed.
		if(defined("DZX25_CONVERTER_PARSER_FIX_DISCUZCODE") && DZX25_CONVERTER_PARSER_FIX_DISCUZCODE)
		{
			if($encoding == 'utf-8')
			{
				// PHP DOM only operate on UTF-8 contents.
				$text = $this->dz_fix_discuzcode($text, $encoding);
			}
		}
		
		// Recover HTML code.
		$text = utf8_unhtmlentities($text);
		
		// Do converter stuff.
		foreach($this->dz_code_standard as $dz_code)
		{
			if(empty($dz_code))
			{
				continue;
			}
			$text_filterd = call_user_func(array($this, 'dz_convert_' . $dz_code), $text);
			$text = $text_filterd === false ? $text : $text_filterd;
		}
		
		foreach($this->dz_code_nestable as $dz_code)
		{
			if(empty($dz_code))
			{
				continue;
			}
			
			$text_filterd = call_user_func(array($this, 'dz_convert_' . $dz_code), $text);
			$text = $text_filterd === false ? $text : $text_filterd;
		}
		
		// Now that we're done, if we split up any code tags, parse them and glue it all back together
		if(count($code_matches) > 0)
		{
			foreach($code_matches as $code_match)
			{
				$code = $code_match[1];
				
				// MyBB breaks parse when two or more [/code] are adjacent at line end.
				// MyBB also breaks parse when [code] is nested.
				// TODO: what to do?
				//$code = str_replace("\n", "<mybb-code-newline>", $code);
				
				$code = '[code]' . $code . '[/code]';
				$text = preg_replace("#\[_mybb_dzx_converter_code_]\n?#", $code, $text, 1);
			}
		}
		$text = str_replace("[-mybb-dzx-converter-code-]\n", "[_mybb_dzx_converter_code_]\n", $text);
		
		return $text;
	}
	
	/**
	 * Discuzcode allows nested code, for MyBB Parser to work, it's best to fix them by removing some.
	 * And for converting any disuczcode that may be netsted, it also needs to be fixed. The following needs fix:
	 * 		discuzcode				tag converted to HTML by Discuz! X2.5		?html tag by converter
	 * 		'[i]'					i											discuz_code_i
	 * 		'[i=s]'					i class="pstatus"							discuz_code_i
	 * 		'[b]'					strong										discuz_code_b
	 * 		'[u]'					u											discuz_code_u
	 * 		'[s]'					strike										discuz_code_s
	 * 		'[size=$1(int px|pt)]'	font style="font-size:$1"					discuz_code_size_pxpt fontsize="$1"
	 * 		'[size=$1]'				font size="$1"								discuz_code_size fontsize="$1"
	 * 
	 * @param string $text The text needs to be fixed.
	 * @param string $encoding The encoding of the $text.
	 * @return string The fixed string.
	 */
	function dz_fix_discuzcode($text, $encoding = 'utf-8')
	{
		// Manipulating only on $html_text.
		$html_text = $text;
		
		// Prepare discuzcode array.
		$discuzcode = array(
				array(
						'regex' => 0,
						'discuzcode' => 'i',
						'html_tag' => 'discuz_code_i',
						'mybbcode' => 'i',
						'allowed_attributes' => array(),
						'remove_sub_tags' => 1,
				),
				array(
						'regex' => 0,
						'discuzcode' => 'i=s',
						'html_tag' => 'discuz_code_i',
						'mybbcode' => 'i',
						'allowed_attributes' => array(),
						'remove_sub_tags' => 1,
				),
				array(
						'regex' => 0,
						'discuzcode' => 'b',
						'html_tag' => 'discuz_code_b',
						'mybbcode' => 'b',
						'allowed_attributes' => array(),
						'remove_sub_tags' => 1,
				),
				array(
						'regex' => 0,
						'discuzcode' => 'u',
						'html_tag' => 'discuz_code_u',
						'mybbcode' => 'u',
						'allowed_attributes' => array(),
						'remove_sub_tags' => 1,
				),
				array(
						'regex' => 0,
						'discuzcode' => 's',
						'html_tag' => 'discuz_code_s',
						'mybbcode' => 's',
						'allowed_attributes' => array(),
						'remove_sub_tags' => 1,
				),
				array(
						'regex' => "#\[size=(\d{1,2}?)\]#i",
						'discuzcode' => 'size',
						'html_tag' => 'discuz_code_size',
						'mybbcode' => 'size',
						'allowed_attributes' => array(
								array(
										'attribute' => 'fontsize',
										'handler_callback' => 'handle_html_size_value',
								),
						),
						'remove_sub_tags' => 0,
				),
				array(
						'regex' => "#\[size=(\d{1,2}(\.\d{1,2}+)?(px|pt)+?)\]#i",
						'discuzcode' => 'size',
						'html_tag' => 'discuz_code_size_pxpt',
						'mybbcode' => 'size',
						'allowed_attributes' => array(
								array(
										'attribute' => 'fontsize',
										'handler_callback' => 'handle_html_size_pxpt',
								),
						),
						'remove_sub_tags' => 1,
				),
				array(
						'regex' => "#\[color=([\#\w]+?)\]#i",
						'discuzcode' => 'color',
						'html_tag' => 'discuz_code_color',
						'mybbcode' => 'color',
						'allowed_attributes' => array(
								array(
										'attribute' => 'fontcolor',
										'handler_callback' => 'handle_html_color_value',
								),
						),
						'remove_sub_tags' => 0,
				),
				array(
						'regex' => "#\[font=([^a-z0-9 ,\-_'\"\]]+?[^\[\<]+?)\]#si",
						'discuzcode' => 'font',
						'html_tag' => 'discuz_code_font_nonen',
						'mybbcode' => 'font',
						'allowed_attributes' => array(
								array(
										'attribute' => 'fontface-nonen',
										'handler_callback' => 'handle_html_fontface_nonen',
								),
						),
						'remove_sub_tags' => 0,
				),
		);
		
		// Convert any "\n" newline character to a codetag, preventing from DOM to strip it between html tags.
		$html_text = str_replace("\n", "[_mybb_dzx_converter_newline_]", $html_text);
		
		// Convert need-to-fix discuz code into HTMLs.
		$finds = array();
		$replaces = array();
		$html_tags_to_remove = array();
		$mybbcode_recover = array();
		$count_dzcode_to_replace = 0;
		foreach($discuzcode as $code)
		{
			if($code['remove_sub_tags'])
			{
				$html_tags_to_remove[] = $code['html_tag'];
			}
			if(!isset($mybbcode_recover[$code['html_tag']]))
			{
				$mybbcode_recover[$code['html_tag']] = array(
						'code' => '',
						'remove_sub_tags' => 0,
				);
			}
			$mybbcode_recover[$code['html_tag']]['code'] = $code['mybbcode'];
			$mybbcode_recover[$code['html_tag']]['remove_sub_tags'] = $code['remove_sub_tags'];
			
			if(empty($code['regex']))
			{
				$finds[] = "[". $code['discuzcode'] ."]";
				$replaces[] = "<". $code['html_tag'] .">";
				$count_dzcode_to_replace++;
				
				$finds[] = "[/". $code['discuzcode'] ."]";
				$replaces[] = "</". $code['html_tag'] .">";
				$count_dzcode_to_replace++;
			}
			else
			{
				$find = $code['regex'];
				$replace = "<" . $code['html_tag'] . " ";
				$count_attributes = count($code['allowed_attributes']);
				
				if($count_attributes && !isset($mybbcode_recover[$code['html_tag']]['attributes']))
				{
					$mybbcode_recover[$code['html_tag']]['attributes'] = array();
				}
				
				for($i = 0; $i < $count_attributes; $i++)
				{
					$capture = $i + 1;
					$replace .= $code['allowed_attributes'][$i]['attribute'] . '="$' . $capture . '" ';
					$mybbcode_recover[$code['html_tag']]['attributes'][] = array(
							'attribute' => $code['allowed_attributes'][$i]['attribute'],
							'handler' => $code['allowed_attributes'][$i]['handler_callback'],
					);
				}
				$replace = trim($replace) . ">";
				$html_text = preg_replace($find, $replace, $html_text);
				
				$finds[] = "[/". $code['discuzcode'] ."]";
				$replaces[] = "</". $code['html_tag'] .">";
				$count_dzcode_to_replace++;
			}
		}
		if($count_dzcode_to_replace)
		{
			$html_text = str_replace($finds, $replaces, $html_text);
		}
		$html_tags_to_remove = array_unique($html_tags_to_remove);
		
		// Fix any HTML parsing errors using DOM.
		$html_text = '<_mybb_fix_html_>' . $html_text . '</_mybb_fix_html_>';
		$doc = new DOMDocument();
		$doc->preserveWhiteSpace = true;
		libxml_use_internal_errors(true);
		$doc->loadHTML('<?xml encoding="' . $encoding . '" ?>' . $html_text);
		libxml_use_internal_errors(false);
		
		// Remove nested tags that are not naively supported by MyBB.
		foreach($html_tags_to_remove as $html_tag)
		{
			$this->dz_remove_nested_tags($doc, $doc->getElementsByTagName("_mybb_fix_html_")->item(0), $html_tag, false, false);
		}
		
		// Convert HTMLs back to MyBBCode while handling some attributes, if any handler is set.
		$html_text = $this->dz_nested_tags_as_string($doc->getElementsByTagName("_mybb_fix_html_")->item(0), $mybbcode_recover);
		
		// Recover any "\n" character.
		$html_text = str_replace("[_mybb_dzx_converter_newline_]", "\n", $html_text);
		
		$text = $html_text;
		
		return $text;
	}

	/**
	 * Try to strip any html attribute that are not supported by MyBBCode. Mainly for `img` code in Discuz! 
	 * user signature.
	 * 
	 * @param string $text The text needs to be fixed.
	 * @param string $encoding The encoding of the $text.
	 * @return string The fixed string.
	 */
	function dz_fix_sightml($text, $encoding = 'utf-8')
	{
		if($encoding != 'utf-8')
		{
			// PHP DOM only operate on UTF-8 contents.
			return $text;
		}
		
		// Manipulating only on $html_text.
		$html_text = $text;
		
		// Prepare html entities array.
		$htmlentities = array(
				array(
						'discuzcode' => 'img',
						'html_tag' => 'img',
						'allowed_attributes' => array(
								array(
										'attribute' => 'src',
										'handler_callback' => '',
								),
						),
						'remove_sub_tags' => 0,
						'no_closing_tag' => 1,
				),
				array(
						'discuzcode' => 'br',
						'html_tag' => 'br',
						'allowed_attributes' => array(),
						'remove_sub_tags' => 0,
						'no_closing_tag' => 1,
				),
				array(
						'discuzcode' => 'hr',
						'html_tag' => 'hr',
						'allowed_attributes' => array(),
						'remove_sub_tags' => 0,
						'no_closing_tag' => 1,
				),
		);
		
		// Convert any "\n" newline character to a codetag, preventing from DOM to strip it between html tags.
		$html_text = str_replace("\n", "[_mybb_dzx_converter_newline_]", $html_text);
		
		// Convert need-to-fix discuz code into HTMLs.
		$html_tags_to_fix = array();
		foreach($htmlentities as $entity)
		{
			$html_tags_to_fix[$entity['html_tag']] = array(
					'code' => $entity['discuzcode'],
					'remove_sub_tags' => $entity['remove_sub_tags'],
					'no_closing_tag' => $entity['no_closing_tag'],
					'attributes' => array(),
			);
			
			foreach($entity['allowed_attributes'] as $attribute)
			{
				$html_tags_to_fix[$entity['html_tag']]['attributes'][] = array(
						'attribute' => $attribute['attribute'],
						'handler' => $attribute['handler_callback'],
				);
			}
		}
		
		// Fix any HTML parsing errors using DOM.
		$html_text = '<_mybb_fix_html_>' . $html_text . '</_mybb_fix_html_>';
		$doc = new DOMDocument();
		$doc->preserveWhiteSpace = true;
		libxml_use_internal_errors(true);
		$doc->loadHTML('<?xml encoding="' . $encoding . '" ?>' . $html_text);
		libxml_use_internal_errors(false);
		
		// Remove nested tags that are not naively supported by MyBB.
		foreach($html_tags_to_fix as $html_tagname => $html_tag)
		{
			if($html_tag['remove_sub_tags'])
			{
				$this->dz_remove_nested_tags($doc, $doc->getElementsByTagName("_mybb_fix_html_")->item(0), $html_tagname, false, false);
			}
		}
		
		// Convert HTMLs back to MyBBCode while handling some attributes, if any handler is set.
		$html_text = $this->dz_nested_tags_as_string($doc->getElementsByTagName("_mybb_fix_html_")->item(0), $html_tags_to_fix, 1);
		
		// Recover any "\n" character.
		$html_text = str_replace("[_mybb_dzx_converter_newline_]", "\n", $html_text);
		
		$text = $html_text;
		
		return $text;
	}
	
	
	function dz_convert_html($text)
	{
		$text = parent::convert($text);
		$text = preg_replace("#<script[^\>]*?>(.*?)<\/script>#i", '', $text);
		return $text;
	}
	
	function dz_convert_hide($text)
	{
		// MyBB do not support hide text naively. Remove it.
		$text = preg_replace("#\[hide(=[0-9]*)?\](.*?)\[/hide\]#i", "$2", $text);
		return $text;
	}
	
	/**
	 * @deprecated
	 */
	function dz_convert_size($text)
	{
		// MyBB [size] do not support size unit, it reads only an integer, or a semantic size definition.
		// The integer of size in MyBB will be templated into using `pt` in default template.
		
		// Consulte https://stackoverflow.com/questions/819079/how-to-convert-font-size-10-to-px for converting 
		// old font size attribute to an actual font size in px|pt or a semantic one.
		$converts = array(
				array(
						'find' => "#\[size=([0-9]+)(px|pt)?\](.*?)\[/size\]#si",
						'callback' => 'handle_size',
				),
		);
		
		foreach($converts as $convert)
		{
			while(preg_match($convert['find'], $text))
			{
				$text = preg_replace_callback($convert['find'], array($this, $converts['callback']), $text);
			}
			
		}
		
		return $text;
	}
	
	// TODO: how to?
	function dz_convert_attachurl($text)
	{
		return $text;
	}
	
	function dz_convert_media($text)
	{
		$text = preg_replace("#\[media=([\w,]+)\]\s*([^\[\<\r\n]+?)\s*\[\/media\]#si", "[video=$2]$2[/video]", $text);
		$text = preg_replace("#\[audio(=1)*\]\s*([^\[\<\r\n]+?)\s*\[\/audio\]#si", "[video=$2]$2[/video]", $text);
		return $text;
	}
	
	function dz_convert_img($text)
	{
		$text = preg_replace(
				array(
					"#\[img\]\s*([^\[\<\r\n]+?)\s*\[\/img\]#si",
					"#\[img=(\d{1,4})[x|\,](\d{1,4})\]\s*([^\[\<\r\n]+?)\s*\[\/img\]#si",
					),
				array(
					"[img]$1[/img]",
					"[img=$1x$2]$3[/img]",
				), 
				$text);
		
		return $text;
	}
	
	function dz_convert_indent($text)
	{
		// Discuz! X2.5 use <blockquote> for [indent]. Change it to [quote]?
		$converts = array(
				array(
						'find' => "#\[indent\](.*?)\[/indent\]#si",
						'replacement' => "[quote]$1[/quote]",
				),
		);
		
		foreach($converts as $convert)
		{
			while(preg_match($convert['find'], $text))
			{
				$text = preg_replace($convert['find'], $convert['replacement'], $text);
			}
			
		}
		
		return $text;
		
	}
	
	/**
	 * @deprecated 
	 */
	function dz_convert_i($text)
	{
		$converts = array(
				array(
						'find' => "#\[i=s\](.*?)\[/i\]#si",
						'replacement' => "[i]$1[/i]",
				),
		);
		
		foreach($converts as $convert)
		{
			while(preg_match($convert['find'], $text))
			{
				$text = preg_replace($convert['find'], $convert['replacement'], $text);
			}
			
		}
		
		return $text;
	}
	
	function dz_convert_align($text)
	{
		$converts = array(
				array(
					'find' => "#\[float=(left|right)\](.*?)\[/float\]#si",
					'replacement' => "[align=$1]$2[/align]",
					),
				array(
					'find' => "#\[p=(\d{1,2}|null), (\d{1,2}|null), (left|center|right)\](.*?)\[/p\]#si",
					'replacement' => "[align=$3]$4[/align]",
					),
				);
		
		foreach($converts as $convert)
		{
			while(preg_match($convert['find'], $text))
			{
				$text = preg_replace($convert['find'], $convert['replacement'], $text);
			}
			
		}
		
		return $text;
	}
	
	function dz_convert_color($text)
	{
		$converts = array(
				array(
						'find' => "#\[color=(rgb\([\d\s,]+?\))\](.*?)\[/color\]#si",
						'callback' => 'handle_color_rgb',
				),
		);
		
		foreach($converts as $convert)
		{
			while(preg_match($convert['find'], $text))
			{
				$text = preg_replace_callback($convert['find'], array($this, $converts['callback']), $text);
			}
		}
		
		return $text;
	}
	
	function dz_convert_backcolor($text)
	{
		$converts = array(
				array(
						'find' => "#\[backcolor=([\#\w]+?)\](.*?)\[/backcolor\]#si",
						'replacement' => "$2",
				),
				array(
						'find' => "#\[backcolor=(rgb\([\d\s,]+?\))\](.*?)\[/backcolor\]#si",
						'replacement' => "$2",
				),
		);
		
		foreach($converts as $convert)
		{
			while(preg_match($convert['find'], $text))
			{
				$text = preg_replace($convert['find'], $convert['replacement'], $text);
			}
			
		}
		
		return $text;
	}
	
	/**
	 * Callback for color discuzcode using a RGB() value.
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
	 * @deprecated
	 * Callback for size bbcode
	 *
	 * @param array $matches
	 *
	 * @return string
	 */
	function handle_size($matches)
	{
		$size = (int)$matches[1];
		if(empty($matches[2]))
		{
			// No px or pt found.
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
		}
		else if($matches[2] == 'px')
		{
			// It's a px value.
			if($size == 1)
			{
				$size = 1;
			}
			else
			{
				$size = intval($size * 0.75);
			}
			
			if($size < 1)
			{
				$size = 1;
			}
		}
		
		if(is_int($size) && $size > 50)
		{
			$size = 50;
		}
		
		return "[size={$size}]{$matches[3]}[/size]";
	}
	/**
	 * Callback for html size attribute without a unit.
	 *
	 * @param mixed $size
	 *
	 * @return string
	 */
	function handle_html_size_value($size)
	{
		$size = (int)$size;
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
		return $size;
	}
	
	/**
	 * Callback for html color attribute.
	 *
	 * @param string $size
	 *
	 * @return string
	 */
	function handle_html_color_value($color)
	{
		if($color[0] != '#')
		{
			return $color;
		}
		
		$color = ltrim($color, '#');
		
		if(strlen($color) < 3)
		{
			$color = '';
		}
		else if(strlen($color) == 4)
		{
			$color = $color . $color[2] . $color[3];
		}
		else if(strlen($color) == 5)
		{
			$color = $color . $color[4];
		}
		else if(strlen($color) > 6)
		{
			$color = substr($color, 0, 6);
		}
		
		if(!empty($color))
		{
			$color = '#'. $color;
		}
		
		return $color;
	}
	
	/**
	 * Callback for html size attribute with a px or pt unit.
	 *
	 * @param string $size
	 *
	 * @return string
	 */
	function handle_html_size_pxpt($size)
	{
		if(strlen($size) > 2)
		{
			$unit = substr($size, -2);
			$size = intval(substr($size, 0, -2));
			if($unit == "px")
			{
				// It's a px value.
				if($size == 1)
				{
					$size = 1;
				}
				else
				{
					$size = intval($size * 0.75);
				}
				
				if($size < 1)
				{
					$size = 1;
				}
			}
		}
		
		if(is_int($size) && $size > 50)
		{
			$size = 50;
		}
		
		$size = is_int($size) ? $size : false;
		
		return $size;
	}
	
	/**
	 * Callback for html fontface attribute offfered with a non-English one. Works only with UTF-8 coded font name. Otherwise or the font name is not found in the function, default font will be set. Default fonts please see the converter class.
	 *
	 * @param string $fontface The font's official name used in OSes. Only UTF-8 coded name will be handled.
	 *
	 * @return string The font name in English.
	 */
	function handle_html_fontface_nonen($fontface)
	{
		$fontface_table = array(
				// Both Windows and MacOS X
				'标楷体' => 'DFKai-SB,BiauKai',
				'標楷體' => 'DFKai-SB,BiauKai',
				'仿宋' => 'FangSong,Fang Song',
				'黑体' => 'SimHei,Hei',
				'楷体' => 'KaiTi,SimKai,Kai',
				'宋体' => 'SimSun,Song',
				// Windows
				'新细明体' => 'PMingLiU',
				'新细明体' => 'PMingLiU',
				'微软新细明体' => 'PMingLiU',
				'微軟新細明體' => 'PMingLiU',
				'细明体' => 'MingLiU',
				'細明體' => 'MingLiU',
				'新宋体' => 'NSimSun',
				'仿宋GB2312' => 'FangSong_GB2312',
				'楷体GB2312' => 'KaiTi_GB2312',
				'微软正黑体' => 'Microsoft JhengHei',
				'微軟正黑體' => 'Microsoft JhengHei',
				'微软雅黑' => 'Microsoft YaHei',
				// MacOS X
				'冬青黑体' => 'Hiragino Sans GB',
				'华文细黑' => 'STHeiti Light',
				'华文黑体' => 'STHeiti,Heiti SC',
				'华文楷体' => 'STKaiti',
				'华文宋体' => 'STSong',
				'华文仿宋' => 'STFangsong',
				'丽黑Pro' => 'LiHei Pro',
				'俪黑Pro' => 'LiHei Pro',
				'麗黑Pro' => 'LiHei Pro',
				'儷黑Pro' => 'LiHei Pro',
				'丽宋Pro' => 'LiSong Pro',
				'俪宋Pro' => 'LiSong Pro',
				'麗宋Pro' => 'LiSong Pro',
				'儷宋Pro' => 'LiSong Pro',
				'苹果丽中黑' => 'Apple LiGothic',
				'苹果俪中黑' => 'Apple LiGothic',
				'蘋果麗中黑' => 'Apple LiGothic',
				'蘋果儷中黑' => 'Apple LiGothic',
				'苹果丽细宋' => 'Apple LiSung',
				'苹果俪细宋' => 'Apple LiSung',
				'蘋果麗細宋' => 'Apple LiSung',
				'蘋果儷細宋' => 'Apple LiSung',
				'苹方' => 'PingFang',
				'蘋方' => 'PingFang',
		);
		
		$fontface = str_replace(array(" ", "_"), "", trim($fontface));
		if(!empty($fontface) && array_key_exists($fontface, $fontface_table))
		{
			foreach(explode(",", $fontface_table[$fontface]) as $font);
			{
				$fontface = trim($font) . ", ";
			}
			$fontface = rtrim($fontface, ", ");
		}
		else if(defined("DZX25_CONVERTER_PARSER_DEFAULT_FONTS"))
		{
			$fontface = DZX25_CONVERTER_PARSER_DEFAULT_FONTS;
		}
		else
		{
			$fontface = '';
		}
		
		return $fontface;
	}
	
	/**
	 * Normally not needed, but some boards may call it so it's still here
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	function convert_title($text)
	{
		return $text;
	}
	
	/**
	 * Handles attachment codes. This is a special function to make sure it's called in every parser
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	function handle_attachments($text)
	{
		if(empty($this->attachment))
		{
			return $text;
		}
		
		// Some forums have special codes (eg phpbb doesn't use the id, they use a post counter to identify the attachment)
		// So we allow the respective parser to add a callback
		// Using "o{id}" here to make sure we find this attachment later again
		if(method_exists($this, "attachment_callback"))
		{
			return preg_replace_callback("#{$this->attachment}#i", array($this, "attachment_callback"), $text);
		}
		else
		{
			return preg_replace("#{$this->attachment}#i", "[attachment=o$2]", $text);
		}
	}
	
	function dz_remove_nested_tags($doc, $node, $tag_to_remove, $remove = false, $found = false)
	{
		$count = 0;
		if($node->hasChildNodes())
		{
			$count = $node->childNodes->length;
		}
		else
		{
			return;
		}
		
		$found_tags_ids = array();
		for($i = 0; $i < $count; $i++)
		{
			$childNode = $node->childNodes->item($i);
			$tagname = $childNode->nodeName;
			
			if(!empty($tag_to_remove) && $tagname == $tag_to_remove)
			{
				// Start working with a tag to be removed.
				if($remove)
				{
					$found_tags_ids[] = $i;
					// An ancestor has already make a declaration to remove this tag in his descendants.
					$found = true;
				}
				else
				{
					if(!$found)
					{
						// Set this bit, let his descendants know. He will be survive.
						$remove = true;
					}
				}
			}
			
			if(!empty($childNode->childNodes))
			{
				$this->dz_remove_nested_tags($doc, $childNode, $tag_to_remove, $remove, $found);
			}
		}
		
		if($remove && count($found_tags_ids))
		{
			$newDoc = new DOMDocument();
			$newChildNodes = $newDoc->createElement("Temporary_Parent");
			$newDoc->appendChild($newChildNodes);
			for($i = 0; $i < $count; $i++)
			{
				$childNode = $node->childNodes->item($i);
				
				if(in_array($i, $found_tags_ids))
				{
					// Clone all his children and add into the temporary parent.
					if(!empty($childNode->childNodes))
					{
						$count_child = $childNode->childNodes->length;
						for($j = 0; $j < $count_child; $j++)
						{
							// Clone this node.
							$newnode = $childNode->childNodes->item($j)->cloneNode(true);
							$newChildNodes->appendChild($newDoc->importNode($newnode, true));
						}
					}
				}
				else
				{
					// Clone this node.
					$newnode = $childNode->cloneNode(true);
					$newChildNodes->appendChild($newDoc->importNode($newnode, true));
				}
			}
			
			while(!empty($node->childNodes) && $node->childNodes->length)
			{
				$childNode = $node->childNodes->item(0);
				
				// Remove it.
				//$childNode->parentNode->removeChild($childNode);
				$node->removeChild($childNode);
			}
			
			$count = !empty($newChildNodes->childNodes) && $newChildNodes->childNodes->length ? $newChildNodes->childNodes->length : 0;
			for($i = 0; $i < $count; $i++)
			{
				$childNode = $newChildNodes->childNodes->item($i);
				
				// Add it.
				$node->appendChild($doc->importNode($childNode, true));
			}
		}
	}
	
	function dz_nested_tags_as_string($node, $tag_to_recover, $html = 0)
	{
		$tag_brace = $html ? '<>' : '[]';
		
		$text = '';
		
		$count = 0;
		if($node->hasChildNodes())
		{
			$count = $node->childNodes->length;
		}
		else
		{
			return $text;
		}
		
		for($i = 0; $i < $count; $i++)
		{
			$childNode = $node->childNodes->item($i);
			$tagName = $childNode->nodeName;
			
			if($childNode->nodeType == XML_TEXT_NODE && $tagName == "#text")
			{
				$text .= $childNode->nodeValue;
				
				// A text node shouldn't have children in our DOM.
				continue;
			}
			
			$tag = '';
			if(array_key_exists($tagName, $tag_to_recover))
			{
				$tag = $tag_to_recover[$tagName];
				$extra = '';
				if($childNode->nodeType == XML_ELEMENT_NODE && isset($tag['attributes']))
				{
					foreach($tag['attributes'] as $attribute)
					{
						if($childNode->hasAttribute($attribute['attribute']))
						{
							$value = $childNode->getAttribute($attribute['attribute']);
							if(!empty($attribute['handler']))
							{
								$value = call_user_func(array($this, $attribute['handler']), $value);
							}
							if($value !== false)
							{
								if($html)
								{
									$value = $attribute['attribute'] . '="' . $value . '"';
								}
								$extra .= $value . ($html ? " " : ",");
							}
						}
					}
				}
				if(!empty($extra))
				{
					$extra = ($html ? " " : "=") . rtrim($extra, ", ");
				}
				if($html && isset($tag['no_closing_tag']) && $tag['no_closing_tag'])
				{
					$text .= $tag_brace[0] . $tag['code'] . $extra . ' /' . $tag_brace[1];
				}
				else
				{
					$text .= $tag_brace[0] . $tag['code'] . $extra . $tag_brace[1];
				}
			}
			else
			{
				// Shouldn't go into this part in our DOM if $html is 0.
				// Output them as originals.
				$extra = '';
				
				if($childNode->nodeType == XML_ELEMENT_NODE)
				{
					foreach ($childNode->attributes as $attribute)
					{
						$name = $attribute->nodeName;
						$value = $attribute->nodeValue;
						$extra .= " " . $name . "=\"" . $value . "\"";
					}
				}
				$text .= $tag_brace[0] . $tagName . $extra . $tag_brace[1];
			}
			
			if($childNode->hasChildNodes())
			{
				$text .= $this->dz_nested_tags_as_string($childNode, $tag_to_recover, $html);
			}
			
			if($html && isset($tag['no_closing_tag']) && $tag['no_closing_tag'])
			{
				continue;
			}
			
			if(is_array($tag) && !empty($tag))
			{
				$text .= $tag_brace[0] . "/" . $tag['code'] . $tag_brace[1];
			}
			else
			{
				$text .= $tag_brace[0] . "/" . $tagName . $tag_brace[1];
			}
		}
		
		return $text;
	}
	
	/***
	 * Code from Discuz! X2.5. Convert HTMLs used in a user's signature to its equivalent bbcode.
	 * Remove `\n`'s converting, resulting in any `\r` being replaced with empty strings. This doesn't
	 * break HTML parsing, but save many contents that are only in bbcode from ill-formatted.
	 */
	function dz_html2bbcode($text)
	{
		
		$html_s_exp = array(
				"/\<div class=\"quote\"\>\<blockquote\>(.*?)\<\/blockquote\>\<\/div\>/is",
				"/\<a href=\"(.+?)\".*?\<\/a\>/is",
				"/\r/",
				"/<br.*>/siU",
				"/[ \t]*\<img src=\"static\/image\/smiley\/comcom\/(.+?).gif\".*?\>[ \t]*/is",
				"/\s*\<img src=\"(.+?)\".*?\>\s*/is"
		);
		$html_r_exp = array(
				"[quote]\\1[/quote]",
				"\\1",
				'',
				"\n",
				"[em:\\1:]",
				"\n[img]\\1[/img]\n"
		);
		$html_s_str = array('<b>', '</b>', '<i>','</i>', '<u>', '</u>', '&nbsp; &nbsp; &nbsp; &nbsp; ', '&nbsp; &nbsp;', '&nbsp;&nbsp;', '&lt;', '&gt;', '&amp;');
		$html_r_str = array('[b]', '[/b]','[i]', '[/i]', '[u]', '[/u]', "\t", '   ', '  ', '<', '>', '&');
		
		$text = preg_replace($html_s_exp, $html_r_exp, $text);
		$text = str_replace($html_s_str, $html_r_str, $text);
		
		// It's up to the MyBB setting if HTML in a signature is allowed.
		//$text = $this->dz_dhtmlspecialchars($text);
		
		return trim($text);
	}
	
	/***
	 * Code from Discuz! X2.5. Convert HTML entities to its character.
	 */
	function dz_dhtmlspecialchars($string, $flags = null, $encoding = 'gbk')
	{
		if(is_array($string))
		{
			foreach($string as $key => $val) {
				$string[$key] = dhtmlspecialchars($val, $flags);
			}
		}
		else
		{
			if($flags === null)
			{
				$string = str_replace(array('&', '"', '<', '>'), array('&amp;', '&quot;', '&lt;', '&gt;'), $string);
				if(strpos($string, '&amp;#') !== false) {
					$string = preg_replace('/&amp;((#(\d{3,5}|x[a-fA-F0-9]{4}));)/', '&\\1', $string);
				}
			}
			else
			{
				if(PHP_VERSION < '5.4.0')
				{
					$string = htmlspecialchars($string, $flags);
				}
				else
				{
					if(strtolower($encoding) == 'utf-8')
					{
						$charset = 'UTF-8';
					}
					else if(strtolower($encoding) == 'gbk')
					{
						$charset = 'GB2312';
					}
					else if(strtolower($encoding) == 'big5')
					{
						$charset = 'BIG5';
					}
					else
					{
						$charset = 'ISO-8859-1';
					}
					$string = htmlspecialchars($string, $flags, $charset);
				}
			}
		}
		
		return $string;
	}
	
	function get_encoding($table_encoding)
	{
		$table_encoding = strtolower($table_encoding);
		switch($table_encoding)
		{
			case "utf8":
			case "utf8mb4":
				return "utf-8";
				break;
			case "latin1":
				return "iso-8859-1";
				break;
			default:
				return $table_encoding;
		}
	}
}
