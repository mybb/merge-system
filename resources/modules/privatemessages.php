<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 */

define('PM_FOLDER_INBOX',  1);
define('PM_FOLDER_OUTBOX', 2);
define('PM_FOLDER_DRAFTS', 3);
define('PM_FOLDER_TRASH',  4);

define('PM_STATUS_UNREAD',    0);
define('PM_STATUS_READ',      1);
define('PM_STATUS_REPLIED',   3);
define('PM_STATUS_FORWARDED', 4);


class Converter_Module_Privatemessages extends Converter_Module
{
	public $default_values = array(
		'import_pmid' => 0,
		'uid' => 0,
		'toid' => 0,
		'fromid' => 0,
		'recipients' => '',
		'folder' => PM_FOLDER_INBOX,
		'subject' => '',
		'icon' => 0,
		'message' => '',
		'dateline' => 0,
		'deletetime' => 0,
		'status' => PM_STATUS_UNREAD,
		'statustime' => 0,
		'includesig' => 0,
		'smilieoff' => 0,
		'receipt' => 2,
		'readtime' => 0,
		'ipaddress' => '',
	);

	public $integer_fields = array(
		'import_pmid',
		'uid',
		'toid',
		'fromid',
		'folder',
		'icon',
		'dateline',
		'deletetime',
		'status',
		'statustime',
		'includesig',
		'smielieoff',
		'receipt',
		'readtime',
	);

	public $binary_fields = array(
		'ipaddress'
	);

	/**
	 * Insert privatemessages into database
	 *
	 * @param pm The insert array going into the MyBB database
	 */
	public function insert($data)
	{
		global $db, $output;

		$this->debug->log->datatrace('$data', $data);

		$output->print_progress("start", $data[$this->settings['progress_column']]);

		// Call our currently module's process function
		$data = $this->convert_data($data);

		// Should loop through and fill in any values that aren't set based on the MyBB db schema or other standard default values and escape them properly
		$insert_array = $this->prepare_insert_array($data);

		unset($insert_array['import_pmid']);

		$this->debug->log->datatrace('$insert_array', $insert_array);

		$db->insert_query("privatemessages", $insert_array);
		$pmid = $db->insert_id();

		$db->insert_query("privatemessage_trackers", array(
			'pmid' => intval($pmid),
			'import_pmid' => intval($data['import_pmid']),
		));

		$this->increment_tracker('privatemessages');

		$output->print_progress("end");

		return $pmid;
	}
}

?>