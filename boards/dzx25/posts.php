<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2019 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class DZX25_Converter_Module_Posts extends Converter_Module_Posts {
	
	var $settings = array(
			'friendly_name' => 'posts',
			'progress_column' => 'pid',
			'default_per_screen' => 5000,
			'check_table_type' => 'forum_post',
	);
	
	function import()
	{
		global $import_session;
		
		$query = $this->old_db->simple_select("forum_post", "*", "", array('order_by' => 'pid', 'order_dir' => 'ASC', 'limit_start' => $this->trackers['start_posts'], 'limit' => $import_session['posts_per_screen']));
		while($post = $this->old_db->fetch_array($query))
		{
			$this->insert($post);
		}
	}
	
	function convert_data($data)
	{
		global $import_session;
		
		$insert_data = array();
		
		// Discuz! values.
		$insert_data['import_pid'] = $data['pid'];
		$insert_data['import_uid'] = $data['authorid'];
		
		$insert_data['tid'] = $this->get_import->tid($data['tid']);
		$insert_data['fid'] = $this->get_import->fid($data['fid']);
		$insert_data['subject'] = encode_to_utf8($data['subject'], "forum_post", "posts");
		$insert_data['uid'] = $this->get_import->uid($data['authorid']);
		if(!empty($insert_data['uid']))
		{
			$insert_data['username'] = $this->get_import->username($insert_data['import_uid'], $data['author']);
		}
		else
		{
			$insert_data['username'] = encode_to_utf8($data['author'], "forum_post", "posts");
		}
		$insert_data['dateline'] = $data['dateline'];
		$insert_data['message'] = encode_to_utf8($data['message'], "forum_post", "posts");
		$insert_data['message'] = $this->bbcode_parser->convert_post($insert_data['message'], $import_session['encode_to_utf8'] ? 'utf-8' : $this->board->fetch_table_encoding($this->settings['encode_table']));
		$insert_data['ipaddress'] = my_inet_pton($data['useip']);
		$insert_data['includesig'] = $data['usesig'];
		$insert_data['smilieoff'] = $data['allowsmilie'] == 1 ? 1 : 0;
		if($data['invisible'] == 0)
		{
			$insert_data['visible'] = 1;
		}
		else if($data['invisible'] == -2)
		{
			$insert_data['visible'] = 0;
		}
		else
		{
			if(($data['first'] == 1 && $data['invisible'] == -1) || ($data['first'] == 0 && $data['invisible'] == -5))
			{
				$insert_data['visible'] = -1;
			}
		}
		
		return $insert_data;
	}
	
	function after_insert($data, $insert_data, $pid)
	{
		global $db;
		
		// Restore first post connections
		$db->update_query("threads", array('firstpost' => $pid), "tid = '{$insert_data['tid']}' AND import_firstpost = '{$insert_data['import_pid']}'");
		if($db->affected_rows() == 0)
		{
			$query = $db->simple_select("threads", "firstpost", "tid = '{$insert_data['tid']}'");
			$first_post = $db->fetch_field($query, "firstpost");
			$db->free_result($query);
			$db->update_query("posts", array('replyto' => $first_post), "pid = '{$pid}'");
		}
	}
	
	function fetch_total()
	{
		global $import_session;
		
		// Get number of posts
		if(!isset($import_session['total_posts']))
		{
			$query = $this->old_db->simple_select("forum_post", "COUNT(*) as count");
			$import_session['total_posts'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}
		
		return $import_session['total_posts'];
	}
	
	function cleanup()
	{
		global $output;
		
		// General output and our progress bar can be constructed here
		$output->print_header("Threads and posts cleanup, rebuild and recount");
		$this->debug->log->trace0("Threads and posts cleanup, rebuild and recount");
		$output->construct_progress_bar();
		echo "<br />\nCleaning threads and rebuild internal counters...(This may take a while)<br />";
		
		flush();
		
		$this->clean_nopost_thread();

		parent::cleanup();
	}
	
	private function clean_nopost_thread()
	{
		global $db, $output, $import_session, $lang;
		
		// Total number of imported threads is needed for percentage
		$query = $db->simple_select("threads", "COUNT(*) as count", "import_tid > 0");
		$num_imported_threads = $db->fetch_field($query, "count");
		$last_percent = 0;

		// Have we finished already (redirects...)?
		if(!isset($import_session['clean_threads_noposts_start']))
		{
			$import_session['clean_threads_noposts_start'] = 0;
		}
		if($import_session['clean_threads_noposts_start'] >= $num_imported_threads)
		{
			return;
		}
		
		$this->debug->log->trace1("Clean threads with no posts in it, searching for threads starting from #" . $import_session['clean_threads_noposts_start']);
		echo "Clean threads with no posts in it, searching for any...";
		flush();
		
		if(!isset($import_session['threads_to_clean']))
		{
			$import_session['threads_to_clean'] = array();
		}
		
		// Get all threads for this page (1000 per page)
		$progress = 0;
		$query = $db->simple_select("threads", "tid,closed", "import_tid > 0", array('order_by' => 'tid', 'order_dir' => 'asc', 'limit_start' => (int)$import_session['clean_threads_noposts_start'], 'limit' => 1000));
		while($thread = $db->fetch_array($query))
		{
			$clean = false;
			
			// Check if this thread has posts in table `posts`.
			$check_query = $db->simple_select("posts", "COUNT(*) as count", "import_pid > 0 AND tid = {$thread['tid']}", array('limit' => 1));
			if(empty($db->fetch_field($check_query, "count")))
			{
				// Maybe this thread will be deleted.
				$clean = true;
				
				// Check if this thread is assigned with a moved tid. Will not check the moved tid's validity.
				if(!empty($thread['closed']) && strpos($thread['closed'], 'moved|') === 0)
				{
					$moved_tid = substr($thread['closed'], 6);
					if(!empty($moved_tid) && $moved_tid == intval($moved_tid))
					{
						// A moved thread with a looking good previous tid will survive.
						$clean = false;
					}
				}
			}
			$db->free_result($check_query);
			
			if($clean)
			{
				// Add the target to an array, they will be purge after all their kinds are found.
				$import_session['threads_to_clean'][] = $thread['tid'];
			}
			
			// Now inform the user
			++$progress;
			$progress_total = $progress + $import_session['clean_threads_noposts_start'];
			
			// Code comes from Dylan, probably has a reason, simply leave it there
			if(($progress_total % 5) == 0)
			{
				if(($progress_total % 100) == 0)
				{
					check_memory();
				}
				
				// 200 is maximum for the progress bar so *200 and not *100
				$percent = round(($progress_total/$num_imported_threads)*200, 1);
				if($percent != $last_percent)
				{
					$output->update_progress_bar($percent, "Checking thread #" . $thread['tid'] . " for cleaning");
				}
				$last_percent = $percent;
			}
		}

		// Add progress to internal counter and display a notice if we've finished
		$import_session['clean_threads_noposts_start'] += $progress;
		
		if($import_session['clean_threads_noposts_start'] >= $num_imported_threads)
		{
			// Searching is finished, do purging job.
			$this->debug->log->trace1("Deleting threads with no posts in it");
			for($i = 0; $i < count($import_session['threads_to_clean']);)
			{
				$db->delete_query("threads", "tid IN ('".implode("','", array_slice($import_session['threads_to_clean'], $i, 20))."')");
				$this->debug->log->trace2("Threads deleted: ".implode(", ", array_slice($import_session['threads_to_clean'], $i, 20))."");
				$i += 20;
			}
			$this->debug->log->trace1("Finished deleting threads with no posts");
			echo $lang->done;
			flush();
		}
		
		// Always redirect back to this page
		$this->redirect();
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


