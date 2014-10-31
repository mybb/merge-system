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

class BBCode_Parser extends BBCode_Parser_Plain {

	// This contains the attachment bbcode which is handled as special code as the id needs to be changed too
	var $attachment = "\[attachment=([0-9]+)\].*?\[/attachment\]";
	// Cache for attachment codes (pid and counter)
	var $pid;

	/**
	 * Converts messages containing phpBB code to MyBB BBcode
	 *
	 * @param string the text to convert
	 * @param int user id of the text
	 * @return string the converted text
	 */
	function convert($text, $uid=0, $pid=0)
	{
		$text = str_replace(array(':'.$uid, '[/*:m]', '[/list:o]', '[/list:u]'), array('', '', '[/list]', '[/list]'), utf8_unhtmlentities($text));

		// Resett attachment counter
		$this->pid = $pid;

		return parent::convert($text);
	}

	function convert_title($text)
	{
		$text = utf8_unhtmlentities($text);

		return $text;
	}

	// Callback for attachment bbcodes
	function attachment_callback($matches)
	{
		// Sorry guys, without pid nothing to do
		if($this->pid == 0)
			return;

		global $module;

		$options = array(
			"order_by"		=> "attach_id",
			"limit"			=> 1,
			"limit_start"	=> $matches[1],
		);
		$query = $module->old_db->simple_select("attachments", "attach_id", "post_msg_id={$this->pid}", $options);
		$id = $module->old_db->fetch_field($query, "attach_id");
		$module->old_db->free_result($query);

		if($id > 0)
		{
			return "[attachment=o{$id}]";
		}
		else
		{
			// Invalid code, remove it
			return "[ATTACHMENT NOT FOUND]";
		}
	}
}
?>