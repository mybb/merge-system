<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 *
 * Discourse Post Converter
 * Discourse stores all posts in a single `posts` table. Each post has a
 * post_number; post_number = 1 is the thread's first post (topic body).
 * Content is stored as raw Markdown and cooked HTML. We use the cooked HTML
 * and convert it to BBCode via the HTML→BBcode parser.
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class DISCOURSE_Converter_Module_Posts extends Converter_Module_Posts {

	var $settings = array(
		'friendly_name' => 'posts',
		'progress_column' => 'id',
		'default_per_screen' => 1000,
		'check_table_type' => 'posts',
	);

	var $first_post_cache = array();

	function import()
	{
		global $import_session;

		// Import posts for regular topics only, ordered by topic then post number
		$query = $this->old_db->query("
			SELECT p.*, t.archetype, t.title
			FROM ".OLD_TABLE_PREFIX."posts p
			LEFT JOIN ".OLD_TABLE_PREFIX."topics t ON (t.id = p.topic_id)
			WHERE t.archetype = 'regular' AND p.deleted_at IS NULL
			ORDER BY p.topic_id ASC, p.post_number ASC
			LIMIT ".$import_session['posts_per_screen']." OFFSET ".$this->trackers['start_posts']."
		");
		while($post = $this->old_db->fetch_array($query))
		{
			$this->insert($post);
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		$insert_data['import_pid'] = $data['id'];
		$insert_data['tid'] = $this->get_import->tid($data['topic_id']);
		$insert_data['uid'] = $this->get_import->uid($data['user_id']);
		$insert_data['import_uid'] = $data['user_id'];
		$insert_data['username'] = $this->get_import->username($insert_data['import_uid']);
		$insert_data['dateline'] = strtotime($data['created_at']);

		// Message: use cooked HTML and convert to BBCode
		if(!empty($data['cooked']))
		{
			$insert_data['message'] = $this->bbcode_parser->convert($data['cooked']);
		}
		else
		{
			$insert_data['message'] = $data['raw'];
		}

		// Subject from the parent topic (for the first post)
		if($data['post_number'] == 1)
		{
			$insert_data['subject'] = encode_to_utf8($data['title'], "topics", "posts");
		}

		// IP address
		if(!empty($data['ip_address']))
		{
			$insert_data['ipaddress'] = my_inet_pton($data['ip_address']);
		}

		// Visibility
		if($data['hidden'] == 't' || $data['hidden'] === true)
		{
			$insert_data['visible'] = 0;
		}
		else
		{
			$insert_data['visible'] = 1;
		}

		// Edit tracking
		if(!empty($data['edited_at']))
		{
			$insert_data['edittime'] = strtotime($data['edited_at']);
		}

		return $insert_data;
	}

	function after_insert($data, $insert_data, $pid)
	{
		global $db;

		// First post in topic → set thread.firstpost
		if($data['post_number'] == 1)
		{
			$db->update_query("threads", array('firstpost' => $pid), "tid = '{$insert_data['tid']}'");
		}
		else
		{
			// Reply → set replyto to the first post of this thread
			if(!isset($this->first_post_cache[$insert_data['tid']]))
			{
				$query = $db->simple_select("threads", "firstpost", "tid = '{$insert_data['tid']}'");
				$this->first_post_cache[$insert_data['tid']] = $db->fetch_field($query, "firstpost");
				$db->free_result($query);
			}
			$db->update_query("posts", array('replyto' => $this->first_post_cache[$insert_data['tid']]), "pid = '{$pid}'");
		}
	}

	function fetch_total()
	{
		global $import_session;

		if(!isset($import_session['total_posts']))
		{
			$query = $this->old_db->query("
				SELECT COUNT(*) as count
				FROM ".OLD_TABLE_PREFIX."posts p
				LEFT JOIN ".OLD_TABLE_PREFIX."topics t ON (t.id = p.topic_id)
				WHERE t.archetype = 'regular' AND p.deleted_at IS NULL
			");
			$import_session['total_posts'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_posts'];
	}
}
