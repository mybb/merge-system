<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 */

abstract class Converter_Module_Posts extends Converter_Module
{
	public $default_values = array(
		'import_uid' => 0,
		'tid' => 0,
		'replyto' => 0,
		'subject' => '',
		'username' => '',
		'fid' => 0,
		'uid' => 0,
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
		'import_pid',
		'import_uid',
		'tid',
		'replyto',
		'fid',
		'uid',
		'dateline',
		'includesig',
		'smilieoff',
		'edituid',
		'edittime',
		'icon',
		'visible',
	);

	var $mark_as_run_modules = array(
		'attachments',
	);

	/**
	 * Insert post into database
	 *
	 * @param array $data The insert array going into the MyBB database
	 * @return int The new id
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
		$insert_array = $this->prepare_insert_array($data, 'posts');

		$this->debug->log->datatrace('$insert_array', $insert_array);

		$db->insert_query("posts", $insert_array);
		$pid = $db->insert_id();

		$this->get_import->cache_posts[$data['import_pid']] = $pid;

		$this->after_insert($unconverted_values, $converted_values, $pid);

		$this->increment_tracker('posts');

		$output->print_progress("end");

		return $pid;
	}

	/**
	 * Rebuild counters, and lastpost information right after importing posts
	 *
	 */
	public function cleanup()
	{
		global $output, $lang;

		// General output and our progress bar can be constructed here
		$output->print_header($lang->module_post_rebuilding);

		$this->debug->log->trace0("Rebuilding thread, forum, and statistic counters");

		$output->construct_progress_bar();

		echo $lang->module_post_rebuild_counters;

		flush();

		// Rebuild thread counters, forum counters, user post counters, last post* and thread username
		$this->rebuild_thread_counters();
		$this->rebuild_forum_counters();
		$this->rebuild_user_post_counters();
		$this->rebuild_user_thread_counters();
	}

	/**
	 * Rebuild all thread counters
	 */
	private function rebuild_thread_counters()
	{
		global $db, $output, $import_session, $lang;

		// Total number of imported threads is needed for percentage
		$query = $db->simple_select("threads", "COUNT(*) as count", "import_tid > 0");
		$num_imported_threads = $db->fetch_field($query, "count");
		$last_percent = 0;

		// Have we finished already (redirects...)?
		if($import_session['counters_threads_start'] >= $num_imported_threads) {
			return;
		}

		$this->debug->log->trace1("Rebuilding thread counters");
		echo $lang->module_post_rebuilding_thread;
		flush();

		// Get all threads for this page (1000 per page)
		$progress = $import_session['counters_threads_start'];
		$query = $db->simple_select("threads", "tid", "import_tid > 0", array('order_by' => 'tid', 'order_dir' => 'asc', 'limit_start' => (int)$import_session['counters_threads_start'], 'limit' => 1000));
		while($thread = $db->fetch_array($query))
		{
			// Updates "replies", "unapprovedposts", "deletedposts" and firstpost/lastpost data
			rebuild_thread_counters($thread['tid']);

			// Now inform the user
			++$progress;

			// Code comes from Dylan, probably has a reason, simply leave it there
			if(($progress % 5) == 0)
			{
				if(($progress % 100) == 0)
				{
					check_memory();
				}

				// 200 is maximum for the progress bar so *200 and not *100
				$percent = round(($progress/$num_imported_threads)*200, 1);
				if($percent != $last_percent)
				{
					$output->update_progress_bar($percent, $lang->sprintf($lang->module_post_thread_counter, $thread['tid']));
				}
				$last_percent = $percent;
			}
		}

		// Add progress to internal counter and display a notice if we've finished
		$import_session['counters_threads_start'] += $progress;

		if($import_session['counters_threads_start'] >= $num_imported_threads)
		{
			$this->debug->log->trace1("Finished rebuilding thread counters");
			echo $lang->done;
			flush();
		}

		// Always redirect back to this page
		$this->redirect();
	}

	/**
	 * Rebuild forum counters
	 */
	private function rebuild_forum_counters()
	{
		global $db, $output, $lang, $import_session;

		// We've already finished this (redirects...)
		if(isset($import_session['counters_forum'])) {
			return;
		}

		$this->debug->log->trace1("Rebuilding forum counters");
		echo $lang->module_post_rebuilding_forum;
		flush();

		// Only update imported forums
		$query = $db->simple_select("forums", "fid", "import_fid > 0");
		$num_imported_forums = $db->num_rows($query);
		$progress = 0;

		while ($forum = $db->fetch_array($query)) {
			rebuild_forum_counters($forum['fid']);
			++$progress;
			// 200 is maximum and not 100
			$output->update_progress_bar(round(($progress / $num_imported_forums) * 200, 1), $lang->sprintf($lang->module_post_forum_counter, $forum['fid']));
		}

		echo $lang->done;

		// Redirect back to this page but remember that this function has been called
		$this->redirect('counters_forum');
	}

	private function rebuild_user_post_counters()
	{
		global $db, $output, $lang, $import_session;

		// We've already finished this (redirects...)
		if(isset($import_session['counters_user_posts'])) {
			return;
		}

		// Building the usepostcount part of the query
		$query = $db->simple_select("forums", "fid", "usepostcounts = 0");
		while($forum = $db->fetch_array($query))
		{
			$fids[] = $forum['fid'];
		}

		if(isset($fids) && is_array($fids))
		{
			$fids = implode(',', $fids);
		}

		if(!empty($fids))
		{
			$fids = " AND p.fid NOT IN($fids)";
		}
		else
		{
			$fids = "";
		}

		$this->debug->log->trace1("Rebuilding user counters");
		echo $lang->module_post_rebuilding_user_post;
		flush();

		// Only update imported users
		$query = $db->simple_select("users", "uid", "import_uid > 0");
		$num_imported_users = $db->num_rows($query);
		$progress = $last_percent = 0;

		while($user = $db->fetch_array($query))
		{
			// This query is from the ACP
			$query2 = $db->query("
				SELECT COUNT(p.pid) AS post_count
				FROM ".TABLE_PREFIX."posts p
				LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
				WHERE p.uid='{$user['uid']}' AND t.visible > 0 AND p.visible > 0{$fids}
			");

			$num_posts = $db->fetch_field($query2, "post_count");
			$db->free_result($query2);
			$db->update_query("users", array("postnum" => (int)$num_posts), "uid='{$user['uid']}'");

			++$progress;
			// 200 is maximum and not 100
			$percent = round(($progress/$num_imported_users)*200, 1);
			if($percent != $last_percent)
			{
				$output->update_progress_bar($percent, $lang->sprintf($lang->module_post_user_counter, $user['uid']));
			}
			$last_percent = $percent;
		}

		$output->update_progress_bar(200, $lang->please_wait);

		echo $lang->done;
		flush();

		// Redirect back to this page but remember that this function has been called
		$this->redirect('counters_user_posts');
	}

	private function rebuild_user_thread_counters()
	{
		global $db, $output, $lang;

		// Building the usepostcount part of the query
		$query = $db->simple_select("forums", "fid", "usepostcounts = 0");
		while($forum = $db->fetch_array($query))
		{
			$fids[] = $forum['fid'];
		}

		if(isset($fids) && is_array($fids))
		{
			$fids = implode(',', $fids);
		}

		if(!empty($fids))
		{
			$fids = " AND t.fid NOT IN($fids)";
		}
		else
		{
			$fids = "";
		}

		$this->debug->log->trace1("Rebuilding user thread counters");
		echo $lang->module_post_rebuilding_user_thread;
		flush();

		// Only update the imported users
		$query = $db->simple_select("users", "uid", "import_uid > 0");
		$num_imported_users = $db->num_rows($query);
		$progress = $last_percent = 0;

		while($user = $db->fetch_array($query))
		{
			// Query from the acp
			$query2 = $db->query("
				SELECT COUNT(t.tid) AS thread_count
				FROM ".TABLE_PREFIX."threads t
				WHERE t.uid='{$user['uid']}' AND t.visible > 0 AND t.closed NOT LIKE 'moved|%'{$fids}
			");
			$num_threads = $db->fetch_field($query2, "thread_count");
			$db->free_result($query2);
			$db->update_query("users", array("threadnum" => (int)$num_threads), "uid='{$user['uid']}'");


			++$progress;
			// 200 is maximum and not 100
			$percent = round(($progress/$num_imported_users)*200, 1);
			if($percent != $last_percent)
			{
				$output->update_progress_bar($percent, $lang->sprintf($lang->module_post_user_counter, $user['uid']));
			}
			$last_percent = $percent;
		}

		$output->update_progress_bar(200, $lang->please_wait);

		echo $lang->done;
		flush();

		// Not needed as this is the latest rebuilding so we need to continue the normal code
		// If a new counter function is called after this we'd need to uncomment this
//		$this->redirect('counters_users_threads');
	}

	private function redirect($finished = "")
	{
		// Do we want to save that we've finished function?
		if(!empty($finished)) {
			global $import_session;
			$import_session[$finished] = 1;
		}

		// Make sure we save changed imports
		update_import_session();

		// Redirect back here - parameters are saved in the session
		if(!headers_sent())
		{
			header("Location: index.php");
		}
		else
		{
			echo "<meta http-equiv=\"refresh\" content=\"0; url=index.php\">";;
		}

		// Stop here!
		exit;
	}
}


