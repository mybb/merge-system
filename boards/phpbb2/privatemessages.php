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

class PHPBB2_Converter_Module_Privatemessages extends Converter_Module_Privatemessages {

	var $settings = array(
		'friendly_name' => 'private messages',
		'progress_column' => 'privmsgs_id',
		'default_per_screen' => 1000,
	);

	function import()
	{
		global $import_session;

		$query = $this->old_db->query("
			SELECT *
			FROM ".OLD_TABLE_PREFIX."privmsgs p
			LEFT JOIN ".OLD_TABLE_PREFIX."privmsgs_text pt ON(p.privmsgs_id=pt.privmsgs_text_id)
			WHERE p.privmsgs_type IN (0,1,2)
			LIMIT ".$this->trackers['start_privatemessages'].", ".$import_session['privatemessages_per_screen']
		);
		while($pm = $this->old_db->fetch_array($query))
		{
			$this->insert($pm);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// phpBB 2 values
		$insert_data['pmid'] = null;
		$insert_data['import_pmid'] = $data['privmsgs_id'];
		$insert_data['uid'] = $this->get_import->uid($data['privmsgs_to_userid']);
		$insert_data['fromid'] = $this->get_import->uid($data['privmsgs_from_userid']);
		$insert_data['toid'] = $this->get_import->uid($data['privmsgs_to_userid']);
		$insert_data['recipients'] = 'a:1:{s:2:"to";a:1:{i:0;s:'.strlen($insert_data['toid']).':"'.$insert_data['toid'].'";}}';
		$insert_data['subject'] = encode_to_utf8($data['privmsgs_subject'], "privmsgs", "privatemessages");
		$insert_data['status'] = $this->get_pm_status($data['privmsgs_type']);
		$insert_data['dateline'] = $data['privmsgs_date'];
		$insert_data['message'] = encode_to_utf8($this->bbcode_parser->convert($data['privmsgs_text'], $data['privmsgs_bbcode_uid']), "privmsgs_text", "privatemessages");
		$insert_data['includesig'] = $data['privmsgs_attach_sig'];
		$insert_data['smilieoff'] = int_to_01($data['privmsgs_enable_smilies']);

		if($data['privmsgs_type'] != 1)
		{
			$insert_data['readtime'] = $insert_data['dateline'];
		}
		else
		{
			$insert_data['readtime'] = 0;
		}

		if($data['privmsgs_type'] != 2)
		{
			$insert_data['folder'] = 1;
		}
		else
		{
			$insert_data['folder'] = 2;
		}

		return $insert_data;
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of private messages
		if(!isset($import_session['total_privatemessages']))
		{
			$query = $this->old_db->simple_select("privmsgs", "COUNT(*) as count", "privmsgs_type IN (0,1,2)");
			$import_session['total_privatemessages'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_privatemessages'];
	}

	function get_pm_status($type)
	{
		switch($type)
		{
			case 0:
			case 2:
			case 3:
			case 4:
				return 1;
				break;
			case 1:
			case 5:
				return 0;
				break;
		}
	}
}


?>