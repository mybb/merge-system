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
	
	function cleanup()
	{
		global $db;
		
		// Cache tread ids.
		$tids = array();
		$query = $db->simple_select("posts", "tid", "import_pid != 0");
		while($post = $db->fetch_array($query))
		{
			$tids[] = $post['tid'];
		}
		$db->free_result($query);
		
		// Delete any thread that has no post in posts table, i.e., no its first post atleast.
		$query = $db->simple_select("threads", "tid,closed", "import_tid != 0");
		while($thread = $db->fetch_array($query))
		{
			$clean = false;
			if(array_search($thread['tid'], $tids) === false)
			{
				$clean = true;
				
				// Check if this thread is assigned with a moved tid. Will not check this tid's validity.
				if(!empty($thread['closed']) && strpos($thread['closed'], 'moved|') === 0)
				{
					$moved_tid = substr($thread['closed'], 6);
					if(!empty($moved_tid) && $moved_tid == intval($moved_tid))
					{
						$clean = false;
					}
				}
			}
			
			if($clean)
			{
				$db->delete_query("threads", "tid = {$thread['tid']}");
			}
		}
		$db->free_result($query);
		
		parent::cleanup();
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
}


