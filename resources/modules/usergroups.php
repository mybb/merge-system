<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 */

class Converter_Module_Usergroups extends Converter_Module
{
	public $default_values = array(
		'import_gid' => 0,
		'title' => '',
		'canview' => 1,
		'canpostthreads' => 1,
		'canpostreplys' => 1,
		'caneditposts' => 1,
		'candeleteposts' => 1,
		'candeletethreads' => 1,
		'cansearch' => 1,
		'canviewmemberlist' => 1,
		'caneditattachments' => 1,
		'canpostpolls' => 1,
		'canvotepolls' => 1,
		'canundovotes' => 1,
		'canpostattachments' => 1,
		'canratethreads' => 1,
		'canviewthreads' => 1,
		'canviewprofiles' => 1,
		'candlattachments' => 1,
		'description' => '',
		'namestyle' => '{username}',
		'type' => 2,
		'stars' => 0,
		'starimage' => 'images/star.gif',
		'image' => '',
		'disporder' => 0,
		'isbannedgroup' => 0,
		'canusepms' => 1,
		'cansendpms' => 1,
		'cantrackpms' => 1,
		'candenypmreceipts' => 1,
		'pmquota' => 0,
		'maxpmrecipients' => 5,
		'cansendemail' => 1,
		'canviewcalendar' => 1,
		'canaddevents' => 1,
		'canviewonline' => 1,
		'canviewwolinvis' => 0,
		'canviewonlineips' => 0,
		'cancp' => 0,
		'issupermod' => 0,
		'canusercp' => 1,
		'canuploadavatars' => 1,
		'canratemembers' => 1,
		'canchangename' => 0,
		'showforumteam' => 0,
		'usereputationsystem' => 1,
		'cangivereputations' => 1,
		'reputationpower' => 1,
		'maxreputationsday' => 5,
		'maxreputationsperuser' => 5,
		'maxreputationsperthread' => 5,
		'candisplaygroup' => 1,
		'attachquota' => 0,
		'cancustomtitle' => 1,
	);

	public $integer_fields = array(
		'import_gid',
		'canview',
		'canpostthreads',
		'canpostreplys',
		'caneditposts',
		'candeleteposts',
		'candeletethreads',
		'cansearch',
		'canviewmemberlist',
		'caneditattachments',
		'canpostpolls',
		'canvotepolls',
		'canundovotes',
		'canpostattachments',
		'canratethreads',
		'canviewthreads',
		'canviewprofiles',
		'candlattachments',
		'type',
		'stars',
		'disporder',
		'isbannedgroup',
		'canusepms',
		'cansendpms',
		'cantrackpms',
		'candenypmreceipts',
		'pmquota',
		'maxpmrecipients',
		'cansendemail',
		'canviewcalendar',
		'canaddevents',
		'canviewonline',
		'canviewwolinvis',
		'canviewonlineips',
		'cancp',
		'issupermod',
		'canusercp',
		'canuploadavatars',
		'canratemembers',
		'canchangename',
		'showforumteam',
		'usereputationsystem',
		'cangivereputations',
		'reputationpower',
		'maxreputationsday',
		'maxreputationsperuser',
		'maxreputationsperthread',
		'candisplaygroup',
		'attachquota',
		'cancustomtitle',
	);

	/**
	 * Insert usergroup into database
	 *
	 * @param group The insert array going into the MyBB database
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

		$this->debug->log->datatrace('$insert_array', $insert_array);

		$db->insert_query("usergroups", $insert_array);
		$gid = $db->insert_id();

		// Update internal array cache
		$this->cache_gids[$group['import_gid']] = $gid; // TODO: Fix?

		$output->print_progress("end");

		$this->increment_tracker('usergroups');

		return $gid;
	}
}

?>