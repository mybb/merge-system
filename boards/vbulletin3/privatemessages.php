<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2011 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class VBULLETIN3_Converter_Module_Privatemessages extends Converter_Module_Privatemessages {

	var $settings = array(
		'friendly_name' => 'private messages',
		'progress_column' => 'pmid',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;

			$query = $this->old_db->query("
				SELECT *
				FROM ".OLD_TABLE_PREFIX."pm p
				LEFT JOIN ".OLD_TABLE_PREFIX."pmtext pt ON(p.pmtextid=pt.pmtextid)
				LIMIT ".$this->trackers['start_privatemessages'].", ".$import_session['privatemessages_per_screen']
			);
			while($pm = $this->old_db->fetch_array($query))
			{
				$this->insert($pm);
			}
	}

	function convert_data($data)
	{
		global $db;

		// vBulletin 3 values
		$insert_data['import_pmid'] = $data['pmid'];
		$insert_data['uid'] = $this->get_import->uid($data['userid']);
		$insert_data['fromid'] = $this->get_import->uid($data['fromuserid']);
		$insert_data['toid'] = $this->get_import->uid($data['touserid']);
		$touserarray = unserialize($data['touserarray']);

		// Rebuild the recipients array
		$recipients = array();
		if(is_array($touserarray['cc']) && !empty($touserarray['cc']))
		{
			foreach($touserarray['cc'] as $key => $to)
			{
				$username = $this->get_username($to);
				$recipients['to'][] = $this->get_import->uid($username['userid']);
			}
		}
		$insert_data['recipients'] = serialize($recipients);

		if($data['folderid'] == -1)
		{
			$insert_data['folder'] = 2;
		}
		else
		{
			$insert_data['folder'] = 0;
		}

		$insert_data['subject'] = encode_to_utf8($data['subject'], "pm", "privatemessages");
		$insert_data['status'] = $data['messageread'];
		$insert_data['dateline'] = $data['dateline'];
		$insert_data['message'] = encode_to_utf8($this->bbcode_parser->convert($data['message']), "pmtext", "privatemessages");
		$insert_data['includesig'] = $data['showsignature'];
		$insert_data['smilieoff'] = int_to_01($data['allowsmilie']);

		if($data['messageread'] == 1)
		{
			$insert_data['readtime'] = time();
		}

		return $insert_data;
	}

	/**
	 * Get a user from the vB database
	 *
	 * @param int Username
	 * @return array If the username is empty, returns an array of username as Guest.  Otherwise returns the user
	 */
	function get_username($username)
	{
		if(empty($username))
		{
			return array(
				'username' => 'Guest',
				'userid' => 0,
			);
		}

		$query = $this->old_db->simple_select("user", "*", "username = '".$this->old_db->escape_string($username)."'", array('limit' => 1));

		$results = $this->old_db->fetch_array($query);
		$this->old_db->free_result($query);

		return $results;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of private messages
		if(!isset($import_session['total_privatemessages']))
		{
			$query = $this->old_db->simple_select("pm", "COUNT(*) as count");
			$import_session['total_privatemessages'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_privatemessages'];
	}
}

?>