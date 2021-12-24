<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 */

abstract class Converter_Module_Usergroups extends Converter_Module
{
	public $default_values = array(
		'import_gid' => 0,
		'type' => 2,
		'title' => '',
		'description' => '',
		'namestyle' => '{username}',
		'usertitle' => '',
		'stars' => 0,
		'starimage' => 'images/star.png',
		'image' => '',
		'disporder' => 0,

		'isbannedgroup' => 0,
		'canview' => 1,
		'canviewthreads' => 1,
		'canviewprofiles' => 1,
		'candlattachments' => 1,
		'canviewboardclosed' => 1,
		'canpostthreads' => 1,
		'canpostreplys' => 1,
		'canpostattachments' => 1,
		'canratethreads' => 1,
		'modposts' => 0,
		'modthreads' => 0,
		'modattachments' => 0,
		'mod_edit_posts' => 0,
		'caneditposts' => 1,
		'candeleteposts' => 1,
		'candeletethreads' => 1,
		'caneditattachments' => 1,
		'canviewdeletionnotice' => 1,
		'canpostpolls' => 1,
		'canvotepolls' => 1,
		'canundovotes' => 0,
		'canusepms' => 1,
		'cansendpms' => 1,
		'cantrackpms' => 1,
		'candenypmreceipts' => 1,
		'pmquota' => 100,
		'maxpmrecipients' => 5,
		'cansendemail' => 1,
		'cansendemailoverride' => 0,
		'maxemails' => 4,
		'emailfloodtime' => 5,
		'canviewmemberlist' => 1,
		'canviewcalendar' => 1,
		'canaddevents' => 1,
		'canbypasseventmod' => 0,
		'canmoderateevents' => 0,
		'canviewonline' => 1,
		'canviewwolinvis' => 0,
		'canviewonlineips' => 0,
		'cancp' => 0,
		'issupermod' => 0,
		'cansearch' => 1,
		'canusercp' => 1,
		'canuploadavatars' => 1,
		'canratemembers' => 1,
		'canchangename' => 0,
		'canbeinvisible' => 1,
		'canbereported' => 0,
		'canchangewebsite' => 1,
		'showforumteam' => 0,
		'usereputationsystem' => 1,
		'cangivereputations' => 1,
		'candeletereputations' => 1,
		'reputationpower' => 1,
		'maxreputationsday' => 5,
		'maxreputationsperuser' => 0,
		'maxreputationsperthread' => 0,
		'candisplaygroup' => 0,
		'attachquota' => 5000,
		'cancustomtitle' => 0,
		'canwarnusers' => 0,
		'canreceivewarnings' => 1,
		'maxwarningsday' => 0,
		'canmodcp' => 0,
		'showinbirthdaylist' => 0,
		'canoverridepm' => 0,
		'canusesig' => 0,
		'canusesigxposts' => 0,
		'signofollow' => 0,
		'edittimelimit' => 0,
		'maxposts' => 0,
		'showmemberlist' => 1,
		'canmanageannounce' => 0,
		'canmanagemodqueue' => 0,
		'canmanagereportedcontent' => 0,
		'canviewmodlogs' => 0,
		'caneditprofiles' => 0,
		'canbanusers' => 0,
		'canviewwarnlogs' => 0,
		'canuseipsearch' => 0,
	);

	public $integer_fields = array(
		'import_gid',
		'type',
		'stars',
		'disporder',

		'isbannedgroup',
		'canview',
		'canviewthreads',
		'canviewprofiles',
		'candlattachments',
		'canviewboardclosed',
		'canpostthreads',
		'canpostreplys',
		'canpostattachments',
		'canratethreads',
		'modposts',
		'modthreads',
		'modattachments',
		'mod_edit_posts',
		'caneditposts',
		'candeleteposts',
		'candeletethreads',
		'caneditattachments',
		'canviewdeletionnotice',
		'canpostpolls',
		'canvotepolls',
		'canundovotes',
		'canusepms',
		'cansendpms',
		'cantrackpms',
		'candenypmreceipts',
		'pmquota',
		'maxpmrecipients',
		'cansendemail',
		'cansendemailoverride',
		'maxemails',
		'emailfloodtime',
		'canviewmemberlist',
		'canviewcalendar',
		'canaddevents',
		'canbypasseventmod',
		'canmoderateevents',
		'canviewonline',
		'canviewwolinvis',
		'canviewonlineips',
		'cancp',
		'issupermod',
		'cansearch',
		'canusercp',
		'canuploadavatars',
		'canratemembers',
		'canchangename',
		'canbeinvisible',
		'canbereported',
		'canchangewebsite',
		'showforumteam',
		'usereputationsystem',
		'cangivereputations',
		'candeletereputations',
		'reputationpower',
		'maxreputationsday',
		'maxreputationsperuser',
		'maxreputationsperthread',
		'candisplaygroup',
		'attachquota',
		'cancustomtitle',
		'canwarnusers',
		'canreceivewarnings',
		'maxwarningsday',
		'canmodcp',
		'showinbirthdaylist',
		'canoverridepm',
		'canusesig',
		'canusesigxposts',
		'signofollow',
		'edittimelimit',
		'maxposts',
		'showmemberlist',
		'canmanageannounce',
		'canmanagemodqueue',
		'canmanagereportedcontent',
		'canviewmodlogs',
		'caneditprofiles',
		'canbanusers',
		'canviewwarnlogs',
		'canuseipsearch',
	);

	/**
	 * Insert usergroup into database
	 *
	 * @param array $data The insert array going into the MyBB database
	 * @return int The new id
	 */
	public function insert($data)
	{
		global $db, $output;

		$this->debug->log->datatrace('$data', $data);

		$output->print_progress("start", $data[$this->settings['progress_column']]);

		// Call our currently module's process function
		$data = $this->convert_data($data);

		// Should loop through and fill in any values that aren't set based on the MyBB db schema or other standard default values and escape them properly
		$insert_array = $this->prepare_insert_array($data, 'usergroups');

		$this->debug->log->datatrace('$insert_array', $insert_array);

		$db->insert_query("usergroups", $insert_array);
		$gid = $db->insert_id();

		// Update internal array cache
		$this->get_import->cache_gids[$insert_array['import_gid']] = $gid; // TODO: Fix?

		$output->print_progress("end");

		$this->increment_tracker('usergroups');

		return $gid;
	}
}


