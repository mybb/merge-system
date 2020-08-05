<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 */

abstract class Converter_Module_Forums extends Converter_Module
{
	public $default_values = array(
		'import_fid' => 0,
		'name' => '',
		'description' => '',
		'import_pid' => 0,
		'disporder' => 0,
		'linkto' => '',
		'lastpost' => 0,
		'parentlist' => '',
		'lastposter' => '',
		'lastposttid' => 0,
		'lastposteruid' => 0,
		'lastpostsubject' => '',
		'threads' => 0,
		'posts' => 0,
		'type' => 'f',
		'pid' => 0,
		'active' => 1,
		'open' => 1,
		'allowhtml' => 0,
		'allowmycode' => 1,
		'allowsmilies' => 1,
		'allowimgcode' => 1,
		'allowvideocode' => 1,
		'allowpicons' => 1,
		'allowtratings' => 1,
		'usepostcounts' => 1,
		'usethreadcounts' => 1,
		'password' => '',
		'showinjump' => 1,
		'style' => 0,
		'overridestyle' => 0,
		'rulestype' => 0,
		'rules' => '',
		'unapprovedthreads' => 0,
		'unapprovedposts' => 0,
		'defaultdatecut' => 0,
		'defaultsortby' => '',
		'defaultsortorder' => '',
	);

	public $integer_fields = array(
		'import_fid',
		'import_pid',
		'disporder',
		'lastpost',
		'lastposttid',
		'lastposteruid',
		'threads',
		'posts',
		'pid',
		'active',
		'open',
		'allowhtml',
		'allowmycode',
		'allowsmilies',
		'allowimgcode',
		'allowpicons',
		'allowtratings',
		'usepostcounts',
		'usethreadcounts',
		'showinjump',
		'style',
		'overridestyle',
		'rulestype',
		'unapprovedthreads',
		'unapprovedposts',
		'defaultdatecut',
	);

	var $mark_as_run_modules = array(
		'forumperms',
		'threads',
		'moderators',
	);

	/**
	 * Insert forum into database
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
		$insert_array = $this->prepare_insert_array($data, 'forums');

		$this->debug->log->datatrace('$insert_array', $insert_array);

		$db->insert_query("forums", $insert_array);
		$fid = $db->insert_id();

		// Update internal array caches
		$this->get_import->cache_fids[$insert_array['import_fid']] = $fid; // TODO: Fix?

		if($insert_array['type'] == "f")
		{
			$this->get_import->cache_fids_f[$insert_array['import_fid']] = $fid; // TODO: Fix?
		}

		$this->increment_tracker('forums');

		$output->print_progress("end");

		return $fid;
	}

	function fix_ampersand($text)
	{
		return str_replace('&amp;', '&', $text);
	}

	function cleanup()
	{
		global $db;

		$query = $db->simple_select('forums', '*', "type='f' AND pid=0 AND import_fid > 0");

		if($db->num_rows($query) > 0)
		{
			$cat = array(
				"name"			=> "{$this->board->plain_bbname} imported forums",
				"type"			=> "c",
				"description"	=> "This forums were imported from your {$this->board->plain_bbname} installation",
				"pid"			=> 0,
				"parentlist"	=> "1",
				"rules"			=> "",
				"active"		=> 1,
				"open"			=> 1,
			);
			// No "input", so no need to escape
			$cid = $db->insert_query("forums", $cat);
			$db->update_query("forums", array("parentlist" => $this->make_mybb_parent_list($cid)), "fid='{$cid}'");

			while($forum = $db->fetch_array($query))
			{
				// Update the parentlists
				$db->update_query("forums", array("pid" => $cid), "fid='{$forum['fid']}'");
				$db->update_query("forums", array("parentlist" => $this->make_mybb_parent_list($forum['fid'], ",", true)), "fid='{$forum['fid']}'");

				// Rebuild the parentlist of all of the subforums of this forum
				switch($db->type)
				{
					case "sqlite":
					case "pgsql":
						$query = $db->simple_select("forums", "fid", "','||parentlist||',' LIKE '%,{$forum['fid']},%'");
						break;
					default:
						$query = $db->simple_select("forums", "fid", "CONCAT(',',parentlist,',') LIKE '%,{$forum['fid']},%'");
				}

				while($child = $db->fetch_array($query))
				{
					$db->update_query("forums", array("parentlist" => $this->make_mybb_parent_list($child['fid'])), "fid='{$child['fid']}'");
				}
			}
		}
	}

	/**
	 * Builds a CSV parent list for a particular forum.
	 *
	 * @param int $fid The forum ID
	 * @param string $navsep Optional separator - defaults to comma for CSV list
	 * @return string The built parent list
	 */
	function make_mybb_parent_list($fid, $navsep=",", $drop_cache=false)
	{
		global $mypforumcache, $db;

		if(!$mypforumcache || $drop_cache)
		{
			$mypforumcache = array();
			$query = $db->simple_select("forums", "name, fid, pid", "", array("order_by" => "disporder, pid"));
			while($forum = $db->fetch_array($query))
			{
				$mypforumcache[$forum['fid']][$forum['pid']] = $forum;
			}
		}

		reset($mypforumcache);
		reset($mypforumcache[$fid]);
		$navigation = '';

		foreach($mypforumcache[$fid] as $key => $forum)
		{
			if($fid == $forum['fid'])
			{
				if($mypforumcache[$forum['pid']])
				{
					$navigation = $this->make_mybb_parent_list($forum['pid'], $navsep).$navigation;
				}

				if($navigation)
				{
					$navigation .= $navsep;
				}
				$navigation .= $forum['fid'];
			}
		}
		return $navigation;
	}

}
