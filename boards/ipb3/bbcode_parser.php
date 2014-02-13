<?php
/**
 * MyBB 1.6
 * Copyright � 2009 MyBB Group, All Rights Reserved
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

// TODO: Doesn't seem to be working 100%
class BBCode_Parser {

	/**
	 * Unconvert the HTML in posts, back to BBCode
	 * @param ref parser object
	 * @param string post message
	 * @return string post message 
	 */
	function convert($text)
	{
		$sizes_finds = array('[SIZE=1]', '[SIZE=7]', '[SIZE=14]', '<br />', '<br>', '&#153;');
		$sizes_replaces = array('[SIZE=x-small]', '[SIZE=medium]', '[SIZE=xx-large]', "", "", '(tm)');

		// $new_text = $this->unconvert($text);

		$text = str_ireplace($sizes_finds, $sizes_replaces, $text);
		$text = str_ireplace(array('[left]', '[center]', '[right]', '[/left]', '[/center]', '[/right]'), array('[align=left]', '[align=center]', '[align=right]', '[/align]', '[/align]', '[/align]'), $text);
		$text = preg_replace("#\[quote name='(.*?)' date='.*?' timestamp='(\d+)' post='(\d+)'\]#si", "[quote='$1' dateline='$2']", $text);
		$text = preg_replace("#\[url=\"([^\"]+)\"\]#si", "[url=$1]", $text);
		$text = preg_replace("#\[email=\"([^\"]+)\"\]#si", "[email=$1]", $text);
		$text = preg_replace("#<img src='.*?' class='bbc_emoticon' alt='(.*?)' />#si", "$1", $text);
		
		return $text;
	}
	
	/**
	 * Unconvert IPB BBCode functions
	 *
	 */
	function unconvert($text)
	{
		$text = str_replace("<#EMO_DIR#>", "&lt;#EMO_DIR&gt;", $text);
		
		$text = preg_replace(array("#(\s)?<([^>]+?)emoid=\"(.*?)\"([^>]*?)".">(\s)?#is", // problem, \\3 returns Array?
		"#<!--emo&(.*?)-->.+?<!--endemo-->#",
		"#<!--sql-->(.*?)<!--sql1-->(.+?)<!--sql2-->(.*?)<!--sql3-->#eis", 
		"#<!--html-->(.*?)<!--html1-->(.*?)<!--html2-->(.*?)<!--html3-->#e",
		"#<!--Flash (.+?)-->.+?<!--End Flash-->#e",
		"#<img src=[\"'](\S+?)['\"].+?".">#",
		"#<a href=[\"']mailto:(.+?)['\"]>(.*?)</a>#",
		"#<a href=[\"'](http://|https://|ftp://|news://)?(\S+?)['\"].+?".">(.*?)</a>#",
		"#<!--QuoteBegin-->(.+?)<!--QuoteEBegin-->#",
		"#<!--QuoteBegin-{1,2}([^>]+?)\+([^>]+?)-->(.*?)<!--QuoteEBegin-->#",
		"#<!--QuoteBegin-{1,2}([^>]+?)\+-->(.*?)<!--QuoteEBegin-->#",
		"#<!--QuoteEnd-->(.+?)<!--QuoteEEnd-->#",
		"#<!--c1-->(.*?)<!--ec1-->#",
		"#<!--c2-->(.*?)<!--ec2-->#",
		"#<i>(.*?)</i>#is",
		"#<b>(.*?)</b>#is",
		"#<s>(.*?)</s>#is",
		"#<u>(.*?)</u>#is",
		"#(\n){0,}<ul>#",
		"#(\n){0,}<ol type='(a|A|i|I|1)'>#",
		"#(\n){0,}<li>#",
		"#(\n){0,}</ul>(\n){0,}#",
		"#(\n){0,}</ol>(\n){0,}#",
		"#<!--quoteo([^>]+?)?-->(.*?)<!--quotec-->#esi",
		"#<!--sizeo([^>]+?)?-->(.*?)<!--\/sizeo-->#i",
		"#<!--sizec-->(.*?)<!--sizec-->#si", 
		"#<!--coloro([^>]+?)?-->(.*?)<!--\/coloro-->#si",
		"#<!--colorc-->(.*?)<!--\/colorc-->#si",
		"#<!--fonto([^>]+?)?-->(.*?)<!--\/fonto-->#si",
		"#<!--fontc-->(.*?)<!--\/fontc-->#si",
		"#<div align=\"center\">(.*?)</div>#i"), 
				
		array("\\3",
		"\\1",
		"\$this->unconvert_sql_tag(\"\\2\")",
		"\$this->unconvert_html_tag(\"\\2\")",
		"\$this->unconvert_flash_tag('\\1')",
		"\[img\]\\1\[/img\]",
		"\[email=\\1\]\\2\[/email\]",
		"\[url=\\1\\2\]\\3\[/url\]",
		'[quote]',
		"[quote=\\1,\\2]",
		"[quote=\\1]",
		'[/quote]',
		'[code]',
		'[/code]',
		"\[i\]\\1\[/i\]",
		"\[b\]\\1\[/b\]",
		"\[s\]\\1\[/s\]",
		"\[u\]\\1\[/u\]",
		"\\1\[list\]",
		"\\1\[list=\\2\]\n",
		"\n\[*\]",
		"\n\[/list\]\\2",
		"\n\[/list\]\\2",
		"\$this->unconvert_quote_tag(\"\\1\", \"\\2\")",
		"\\2",
		"\\1",
		"\\2",
		"\\1",
		"\\2",
		"\\1",
		"[align=center]\\1[/align]"), $text);
			
		while(preg_match("#<span style=['\"]font-size:(.+?)pt;line-height:100%['\"]>(.+?)</span>#is", $text))
		{
			$text = preg_replace("#<span style=['\"]font-size:(.+?)pt;line-height:100%['\"]>(.+?)</span>#ise", "\$this->unconvert_size_tag('\\1', '\\2')", $text);
		}
			
		while(preg_match("#<span style=['\"]color:(.+?)['\"]>(.+?)</span>#is", $text))
		{
			$text = preg_replace( "#<span style=['\"]color:(.+?)['\"]>(.+?)</span>#is", "\[color=\\1\]\\2\[/color\]", $text);
		}
			
		while(preg_match("#<span style=['\"]font-family:(.+?)['\"]>(.+?)</span>#is", $text))
		{
			$text = preg_replace("#<span style=['\"]font-family:(.+?)['\"]>(.+?)</span>#is", "\[font=\\1\]\\2\[/font\]", $text);
		}

		$text = preg_replace(array("#(\[/QUOTE\])\s*?<br />\s*#si", 
		"#(\[/QUOTE\])\s*?<br>\s*#si", 
		"#<!--EDIT\|.+?\|.+?-->#", 
		"</li>"), array("\\1\n", "\\1\n", "", ""), $text);
        
		return trim(stripslashes($text));
	}
	
	function unconvert_quote_tag($matches=array())
	{
		$quote_data = $matches[1];
		$quote_text = $matches[2];
		
		if(!$quote_data)
		{
			return '[quote]';
		}
		else
		{
			preg_match("#\(post=(.+?)?:date=(.+?)?:name=(.+?)?\)#", $quote_data, $match);			
			return str_replace('  ', ' ', "[quote='{$match[3]}']");
		}
	}
	
	function unconvert_size_tag($size="", $text="")
	{		
		$size -= 7;		
		return "[size={$size}]{$text}[/size]";		
	}

	function unconvert_flash_tag($flash="")
	{	
		$flash_array = explode("+", $flash);		
		return "Flash Code: {$flash_array[2]} ({$flash_array[0]}X{$flash_array[1]}";
	}
	
	function unconvert_sql_tag($sql="")
	{
		return "[code]".preg_replace("#<span style='(.*?)'>#is", "#</span>#i", array("", ""), stripslashes($sql))."[/code]";	
	}

	function unconvert_html_tag($html="")
	{
		return "[code]".preg_replace("#<span style='(.*?)'>#is", "#</span>#i", array("", ""), stripslashes($html))."[/code]";
	}
}
?>