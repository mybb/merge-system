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

class WBB3_Converter extends Converter
{
	/**
	 * The installationnumber which can be set during configuration
	 * It should be detected automatically, however if you experience problems, you can set it here directly
	 * 
	 * @var int
	 */
	var $installationnumber;

	/**
	 * String of the bulletin board name
	 *
	 * @var string
	 */
	var $bbname = "WoltLab Burning Board 3 or Lite 2";

	/**
	 * String of the plain bulletin board name
	 *
	 * @var string
	 */
	var $plain_bbname = "WBB 3 / Lite 2";

	/**
	 * Whether or not this module requires the loginconvert.php plugin
	 *
	 * @var boolean
	 */
	var $requires_loginconvert = true;

	/**
	 * Array of all of the modules
	 *
	 * @var array
	 */
	var $modules = array("db_configuration" => array("name" => "Database Configuration", "dependencies" => ""),
						 "import_users" => array("name" => "Users", "dependencies" => "db_configuration"),
						 "import_usergroups" => array("name" => "Usergroups", "dependencies" => "db_configuration,import_users"),
						 "import_forums" => array("name" => "Forums", "dependencies" => "db_configuration,import_users"),
						 "import_threads" => array("name" => "Threads", "dependencies" => "db_configuration,import_forums"),
						 "import_polls" => array("name" => "Polls", "dependencies" => "db_configuration,import_threads"),
						 "import_pollvotes" => array("name" => "Poll Votes", "dependencies" => "db_configuration,import_polls"),
						 "import_posts" => array("name" => "Posts", "dependencies" => "db_configuration,import_threads"),
						 "import_privatemessages" => array("name" => "Private Messages", "dependencies" => "db_configuration,import_users"),
						 "import_attachments" => array("name" => "Attachments", "dependencies" => "db_configuration,import_posts"),
						);

	/**
	 * The table we check to verify it's "our" database
	 *
	 * @var String
	 */
	var $check_table = "board_ignored_by_user";

	/**
	 * The table prefix we suggest to use
	 *
	 * @var String
	 */
	var $prefix_suggestion = "";
	var $hide_table_prefix = true;
	
	/**
	 * An array of wbb -> mybb groups
	 * 
	 * @var array
	 */
	var $groups = array(
		1 => MYBB_GUESTS, // All
		2 => MYBB_GUESTS, // Guests
		3 => MYBB_REGISTERED, // Registered
		4 => MYBB_ADMINS, // Administrators
		5 => MYBB_MODS, // Moderators
		6 => MYBB_SMODS, // Super Moderators
	);

	function __construct()
	{
		global $import_session;

		parent::__construct();

		// The number was set during the configuration and saved as prefix
		if(empty($this->installationnumber) && !empty($import_session['old_tbl_prefix']))
		{
			$this->installationnumber = (int) substr($import_session['old_tbl_prefix'], 3, 1);
			unset($import_session['old_tbl_prefix']);
			$import_session['wbb_number'] = $this->installationnumber;
		}
		else if(empty($this->installationnumber) && !empty($import_session['wbb_number']))
		{
			$this->installationnumber = (int) $import_session['wbb_number'];
		}
		
		define("WCF_PREFIX", "wcf{$this->installationnumber}_");
		define("WBB_PREFIX", "wbb{$this->installationnumber}_1_");
	}

	/**
	 * Convert a WBB group ID into a MyBB group ID
	 *
	 * @param int Group ID
	 * @param array Options for retreiving the group ids
	 * @return mixed group id(s)
	 */
	function get_group_id($uid, $options=array())
	{
		$query = $this->old_db->simple_select(WCF_PREFIX."user_to_groups", "*", "userID = '{$uid}'");
		if(!$query)
		{
			return MYBB_REGISTERED;
		}

		$groups = array();
		while($wbbgroup = $this->old_db->fetch_array($query))
		{
			if($options['original'] == true)
			{
				$groups[] = $wbbgroup['groupID'];
			}
			else
			{
				$groups[] = $this->get_gid($wbbgroup['groupID']);
			}
		}

		$this->old_db->free_result($query);
		return implode(',', array_unique($groups));
	}
	
	function db_extra()
	{
		global $mybb, $lang;

		if(!empty($this->installationnumber))
		{
		    return;
		}

		if(!isset($mybb->input['installationnumber']))
		{
			$mybb->input['installationnumber'] = 1;
		}

		// This is a hack to fix the table prefix. It's always wbb{num}_1.
		echo '<script type="text/javascript">
			$(function() {
				$("#next_button > .submit_button").click(function(e) {
					var dbengine = $("#dbengine").val();
					$("#config_"+dbengine+"_tableprefix").val("wbb"+$("#installationnumber").val()+"_1_");
				});
			});
		</script>';

		return "<tbody>
		<tr>
			<tr>
				<th colspan=\"2\" class=\"first last\">{$lang->wbb_installationnumber}</th>
			</tr>
			<tr class=\"last\">
				<td class=\"first\"><label for=\"installationnumber\">{$lang->wbb_installationnumber_desc}</label></td>
				<td class=\"last alt_col\"><input type=\"text\" class=\"text_input\" name=\"installationnumber\" id=\"installationnumber\" value=\"".htmlspecialchars_uni($mybb->input['installationnumber'])."\" /></td>
			</td>
		</tr>
		</tbody>";
	}
}

?>