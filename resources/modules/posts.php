<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 */

class Converter_Module_Posts extends Converter_Module
{
	public $default_values = array(
		'tid' => 0,
		'replyto' => 0,
		'subject' => '',
		'username' => '',
		'fid' => 0,
		'uid' => 0,
		'import_uid' => 0,
		'dateline' => 0,
		'message' => '',
		'ipaddress' => '',
		'includesig' => 1,
		'smilieoff' => 0,
		'edituid' => 0,
		'edittime' => 0,
		'icon' => 0,
		'visible' => 1,
	);
	
	public $binary_fields = array(
		'ipaddress',
	);

	public $integer_fields = array(
		'tid',
		'replyto',
		'fid',
		'uid',
		'import_uid',
		'dateline',
		'includesig',
		'smilieoff',
		'edituid',
		'edittime',
		'icon',
		'visible',
	);

	/**
	 * Insert post into database
	 *
	 * @param post The insert array going into the MyBB database
	 */
	public function insert($data)
	{
		global $db, $output;

		$this->debug->log->datatrace('$data', $data);

		$output->print_progress("start", $data[$this->settings['progress_column']]);

		$unconverted_values = $data;

		// Call our currently module's process function
		$data = $converted_values = $this->convert_data($data);

		// Should loop through and fill in any values that aren't set based on the MyBB db schema or other standard default values and escape them properly
		$insert_array = $this->prepare_insert_array($data);

		unset($insert_array['import_pid']);
		unset($insert_array['import_uid']);

		$this->debug->log->datatrace('$insert_array', $insert_array);

		$db->insert_query("posts", $insert_array);
		$pid = $db->insert_id();

		$db->insert_query("post_trackers", array(
			'pid' => intval($pid),
			'import_pid' => intval($data['import_pid']),
			'import_uid' => intval($data['import_uid'])
		));

		$this->cache_posts[$data['import_pid']] = $pid;

		if(method_exists($this, "after_import"))
		{
			$this->after_import($unconverted_values, $converted_values, $pid);
		}

		$this->increment_tracker('posts');

		$output->print_progress("end");

		return $pid;
	}

	/**
	 * Rebuild counters, and lastpost information right after importing posts
	 *
	 */
	public function counters_cleanup()
	{
		global $db, $output, $import_session, $lang;

		$output->print_header($lang->module_post_rebuilding);

		$this->debug->log->trace0("Rebuilding thread, forum, and statistic counters");

		$output->construct_progress_bar();

		echo $lang->module_post_rebuild_counters;

		flush();

		// Rebuild thread counters, forum counters, user post counters, last post* and thread username
		$query = $db->simple_select("threads", "COUNT(*) as count", "import_tid != 0");
		$num_imported_threads = $db->fetch_field($query, "count");
		$progress = $last_percent = 0;

		if($import_session['counters_cleanup_start'] < $num_imported_threads)
		{
			$this->debug->log->trace1("Rebuilding thread counters");

			$progress = $import_session['counters_cleanup_start'];
			$query = $db->simple_select("threads", "tid", "import_tid != 0", array('order_by' => 'tid', 'order_dir' => 'asc', 'limit_start' => intval($import_session['counters_cleanup_start']), 'limit' => 1000));
			while($thread = $db->fetch_array($query))
			{
				rebuild_thread_counters($thread['tid']);

				++$progress;

				if(($progress % 5) == 0)
				{
					if(($progress % 100) == 0)
					{
						check_memory();
					}
					$percent = round(($progress/$num_imported_threads)*100, 1);
					if($percent != $last_percent)
					{
						$output->update_progress_bar($percent, $lang->sprintf($lang->module_post_thread_counter, $thread['tid']));
					}
					$last_percent = $percent;
				}
			}

			$import_session['counters_cleanup_start'] += $progress;

			if($import_session['counters_cleanup_start'] >= $num_imported_threads)
			{
				$this->debug->log->trace1("Finished rebuilding thread counters");
				$import_session['counters_cleanup'] = 0;
				echo $lang->done;
				flush();
				return;
			}
			$import_session['counters_cleanup'] = 1;
			return;
		}

		if($import_session['counters_cleanup_start'] >= $num_imported_threads)
		{
			$this->debug->log->trace1("Rebuilding forum counters");
			echo "{$lang->done}. <br />{$lang->module_post_rebuilding_forum} ";
			flush();

			$query = $db->simple_select("forums", "COUNT(*) as count", "import_fid != 0");
			$num_imported_forums = $db->fetch_field($query, "count");
			$progress = 0;

			$query = $db->simple_select("forums", "fid", "import_fid != 0", array('order_by' => 'fid', 'order_dir' => 'asc'));
			while($forum = $db->fetch_array($query))
			{
				rebuild_forum_counters($forum['fid']);
				++$progress;
				$output->update_progress_bar(round((($progress/$num_imported_forums)*50), 1)+100, $lang->sprintf($lang->module_post_forum_counter, $forum['fid']));
			}

			$output->update_progress_bar(150);

			$query = $db->simple_select("forums", "fid", "usepostcounts = 0");
			while($forum = $db->fetch_array($query))
			{
				$fids[] = $forum['fid'];
			}

			if(is_array($fids))
			{
				$fids = implode(',', $fids);
			}

			if($fids)
			{
				$fids = " AND fid NOT IN($fids)";
			}
			else
			{
				$fids = "";
			}

			$this->debug->log->trace1("Rebuilding user counters");
			echo "{$lang->done}. <br />{$lang->module_post_rebuilding_user} ";
			flush();

			$query = $db->simple_select("users", "COUNT(*) as count", "import_uid != 0");
			$num_imported_users = $db->fetch_field($query, "count");
			$progress = $last_percent = 0;

			$query = $db->simple_select("users", "uid", "import_uid != 0");
			while($user = $db->fetch_array($query))
			{
				$query2 = $db->simple_select("posts", "COUNT(*) AS post_count", "uid='{$user['uid']}' AND visible > 0{$fids}");
				$num_posts = $db->fetch_field($query2, "post_count");
				$db->free_result($query2);
				$db->update_query("users", array("postnum" => intval($num_posts)), "uid='{$user['uid']}'");

				++$progress;
				$percent = round((($progress/$num_imported_users)*50)+150, 1);
				if($percent != $last_percent)
				{
					$output->update_progress_bar($percent, $lang->sprintf($lang->module_post_forum_counter, $user['uid']));
				}
				$last_percent = $percent;
			}
			// TODO: recount user posts doesn't seem to work

			$output->update_progress_bar(200, $lang->please_wait);

			echo "{$lang->done}.<br />";
			flush();

			sleep(3);
		}
	}
}

?>