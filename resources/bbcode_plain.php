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

class BBCode_Parser_Plain {

	// This contains the attachment bbcode which is handled as special code as the id needs to be changed too
	var $attachment = "";

	// We handle some special codes already here eg [s] and [del] are the same for mybb
	function convert($text)
	{
		// IMG tag with alt attribute - remove the attribute
		$text = preg_replace("#\[img=(.*?)\](.*?)\[/img\]#i", "[img]$2[/img]", $text);
		// Change [del] to [s]
		$text = preg_replace("#\[del\](.*?)\[/del\]#i", "[s]$1[/s]", $text);
		// Change [em] to [i]
		$text = preg_replace("#\[em\](.*?)\[/em\]#i", "[i]$1[/i]", $text);
		// Same save list items as "[*]Item[/*]" so we're removing the closing one
		$text = preg_replace("#\[\*\](.*?)\[/\*\]#i", "[*]$1", $text);
		// Wrong align codes
		$text = str_ireplace(array("[center]", "[/center]", "[left]", "[/left]", "[right]", "[/right]"), array("[align=center]", "[/align]", "[align=left]", "[/align]", "[align=right]", "[/align]"), $text);
		// "[url='http://community.mybb.com']" is used by some boards...
		$text = preg_replace("#\[url='(.*?)'\](.*?)\[/url\]#i", "[url=$1]$2[/url]", $text);

		$text = $this->handle_attachments($text);

		return $text;
	}

	// Normally not needed, but some boards may call it so it's still here
	function convert_title($text)
	{
		return $text;
	}

	// Handles attachment codes. This is a special function to make sure it's called in every parser
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
			return preg_replace("#{$this->attachment}#i", "[attachment=o$1]", $text);
		}
	}

	// Replaces the old id with the new id. Is automatically called after the attachment was inserted
	function change_attachment($attachment)
	{
		// No attachment code? Skip this
		if(empty($this->attachment))
		{
			return;
		}

		global $db, $mybb;

		$query = $db->simple_select("posts", "pid,message", "message LIKE '%[attachment=o{$attachment['import_aid']}]%'");
		while($post = $db->fetch_array($query))
		{
			// The attachment is in this post, simply update our bbcodes
			if($post['pid'] == $attachment['pid'])
			{
				$replace = "[attachment={$attachment['aid']}]";
			}
			// The attachment is in another post, some forums allow this (we don't)
			// Link to the attachment either with name or thumbnail
			else
			{
				if(substr($attachment['filetype'], 0, 5) == "image")
				{
					$text = "[img]{$mybb->settings['bburl']}/attachment.php?thumbnail={$attachment['aid']}[/img]";
				}
				else
				{
					$text = "{$attachment['filename']}";
				}
				$replace = "[url={$mybb->settings['bburl']}/attachment.php?aid={$attachment['aid']}]{$text}[/url]";
			}
			$message = str_replace("[attachment=o{$attachment['import_aid']}]", $replace, $post['message']);
			$db->update_query("posts", array("message" => $db->escape_string($message)), "pid={$post['pid']}");
		}
	}
}