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

class Cache_Handler
{
	/**
	 * Cache for the new UIDs
	 */
	var $cache_uids;

	/**
	 * Cache for the new FIDs
	 */
	var $cache_fids;

	/**
	 * Cache for the new type 'f' FIDs
	 */
	var $cache_fids_f;

	/**
	 * Cache for the new type 'c' FIDs
	 */
	var $cache_fids_c;

	/**
	 * Cache for the new TIDs
	 */
	var $cache_tids;

	/**
	 * Cache for the new GIDs
	 */
	var $cache_gids;

	/**
	 * Cache for the new Usernames
	 */
	var $cache_usernames;

	/**
	 * Cache for the new Events
	 */
	var $cache_events;

	/**
	 * Cache for the new Attachments
	 */
	var $cache_attachments;

	/**
	 * Cache for poll information
	 */
	var $cache_polls = array();

	/**
	 * Cache for post attachment information
	 */
	var $cache_post_attachment_details = array();

	/**
	 * Get an array of data needed for attachments from the posts table
	 *
	 * @param int Import Post ID
	 * @return array
	 */
	function post_attachment_details($old_pid)
	{
		global $db;

		if(array_key_exists($pid, $this->cache_post_attachment_details))
		{
			return $this->cache_post_attachment_details[$old_pid];
		}

		$query = $db->simple_select("posts", "tid, uid, pid", "pid = '".$this->pid($old_pid)."'");
		$details = $db->fetch_array($query);
		$db->free_result($query);

		$this->cache_post_attachment_details[$old_pid] = $details;

		return $details;
	}

	/**
	 * Get an array of imported polls (e.x. array vBulletin poll id => MyBB poll id)
	 *
	 * @return array
	 */
	function cache_pollids()
	{
		global $db;

		$query = $db->simple_select("polls", "pid, import_pid", "import_pid>0");
		while($poll = $db->fetch_array($query))
		{
			$polls[$poll['import_pid']] = $poll['pid'];
		}
		$this->cache_pollids = $polls;
		$db->free_result($query);

		return $polls;
	}

	/**
	 * Get the MyBB PID of an old PID.
	 *
	 * @param int Poll ID used before import (e.x. vBulletin poll id)
	 * @return int Poll ID in MyBB or 0 if the old PID cannot be found
	 */
	function pollid($old_pid)
	{
		if(!is_array($this->cache_pollids))
		{
			$this->cache_pollids();
		}

		if(!isset($this->cache_pollids[$old_pid]) || $old_pid == 0)
		{
			return 0;
		}

		return $this->cache_pollids[$old_pid];
	}

	/**
	 * Get the MyBB PID of an old PID.
	 *
	 * @param int Poll ID used before import (e.x. vBulletin poll id)
	 * @return int Poll ID in MyBB or 0 if the old PID cannot be found
	 */
	function poll($old_pid)
	{
		global $db;

		if(!array_key_exists($old_pid, $this->cache_polls))
		{
			$query = $db->simple_select("polls", "*", "pid = '".$this->pollid($data['ID_POLL'])."'");
			$this->cache_polls[$old_pid] = $db->fetch_array($query);
			$db->free_result($query);
		}

		if(!isset($this->cache_polls[$old_pid]) || $old_pid == 0)
		{
			return 0;
		}

		return $this->cache_polls[$old_pid];
	}

	/**
	 * Get an array of imported poll votes (e.x. array vBulletin poll vote id => MyBB poll vote id)
	 *
	 * @return array
	 */
	function cache_pollvotes()
	{
		global $db;

		$query = $db->simple_select("pollvotes", "vid, import_vid", "import_vid>0");
		while($pollvote = $db->fetch_array($query))
		{
			$pollvotes[$pollvote['import_vid']] = $pollvote['vid'];
		}
		$this->cache_pollvotes = $pollvotes;
		$db->free_result($query);

		return $pollvotes;
	}

	/**
	 * Get the MyBB VID of an old VID. (e.x. vBulletin poll vote id)
	 *
	 * @param int Vote ID used before import
	 * @return int Vote ID in MyBB or 0 if the old VID cannot be found
	 */
	function vid($old_vid)
	{
		if(!is_array($this->cache_pollvotes))
		{
			$this->cache_pollvotes();
		}

		if(!isset($this->cache_pollvotes[$old_vid]) || $old_vid == 0)
		{
			return 0;
		}

		return $this->cache_pollvotes[$old_vid];
	}

	/**
	 * Get an array of imported users (e.x. array vBulletin user id => MyBB user id)
	 *
	 * @return array
	 */
	function cache_users()
	{
		global $db;

		$query = $db->simple_select("users", "uid, import_uid", "import_uid>0");
		while($user = $db->fetch_array($query))
		{
			$users[$user['import_uid']] = $user['uid'];
		}
		$this->cache_uids = $users;
		$db->free_result($query);

		return $users;
	}

	/**
	 * Get the MyBB UID of an old UID. (e.x. vBulletin user id)
	 *
	 * @param int User ID used before import
	 * @return int User ID in MyBB or 0 if the old UID cannot be found
	 */
	function uid($old_uid)
	{
		if(!is_array($this->cache_uids))
		{
			$this->cache_users();
		}

		if(!isset($this->cache_uids[$old_uid]) || $old_uid == 0)
		{
			return 0;
		}

		return $this->cache_uids[$old_uid];
	}

	/**
	 * Get an array of imported usernames (e.x. array vBulletin user id => MyBB username)
	 *
	 * @return array
	 */
	function cache_usernames()
	{
		global $db;

		$query = $db->simple_select("users", "username, import_uid", "import_uid>0");
		while($user = $db->fetch_array($query))
		{
			$users[$user['import_uid']] = $user['username'];
		}
		$this->cache_usernames = $users;
		$db->free_result($query);

		return $users;
	}

	/**
	 * Get the MyBB Username of an old UID. (e.x. vBulletin user id)
	 *
	 * @param int User ID used before import
	 * @param string Username used before import
	 * @return string Username in MyBB or the old username (if provided)/'Guest' if the old UID cannot be found
	 */
	function username($old_uid, $old_username="")
	{
		if(!is_array($this->cache_usernames))
		{
			$this->cache_usernames();
		}

		if(!isset($this->cache_usernames[$old_uid]) || !$old_uid)
		{
			if($old_username)
			{
				return $old_username;
			}
			// Otherwise, just return 'Guest' to be safe
			return 'Guest';
		}

		return $this->cache_usernames[$old_uid];
	}

	/**
	 * Get an array of imported forums (e.x. array vBulletin forum id => MyBB forum id)
	 *
	 * @return array
	 */
	function cache_forums()
	{
		global $db;

		$query = $db->simple_select("forums", "fid, import_fid", "import_fid>0");
		while($forum = $db->fetch_array($query))
		{
			$forums[$forum['import_fid']] = $forum['fid'];
		}
		$this->cache_fids = $forums;
		$db->free_result($query);

		return $forums;
	}

	/**
	 * Get the MyBB FID of an old FID. (e.x. vBulletin forum id)
	 *
	 * @param int Forum ID used before import
	 * @return int Forum ID in MyBB
	 */
	function fid($old_fid)
	{
		if(!is_array($this->cache_fids))
		{
			$this->cache_forums();
		}

		return $this->cache_fids[$old_fid];
	}

	/**
	 * Get an array of imported forums of type 'f' only (forums only, not categories) (e.x. array vBulletin forum id => MyBB forum id)
	 *
	 * @return array
	 */
	function cache_forums_f()
	{
		global $db;

		$query = $db->simple_select("forums", "fid, import_fid", "import_fid>0 AND type='f'");
		while($forum = $db->fetch_array($query))
		{
			$forums[$forum['import_fid']] = $forum['fid'];
		}
		$this->cache_fids_f = $forums;
		$db->free_result($query);

		return $forums;
	}

	/**
	 * Get the MyBB FID of an old FID. (e.x. vBulletin forum id [forums only, not categories])
	 *
	 * @param int Forum ID used before import
	 * @return int Forum ID in MyBB
	 */
	function fid_f($old_fid)
	{
		if(!is_array($this->cache_fids_f))
		{
			$this->cache_forums_f();
		}

		return $this->cache_fids_f[$old_fid];
	}

	/**
	 * Get an array of imported forums of type 'c' only (categories only, not forums) (e.x. array vBulletin category id => MyBB category id)
	 *
	 * @return array
	 */
	function cache_forums_c()
	{
		global $db;

		$query = $db->simple_select("forums", "fid, import_fid", "import_fid>0 AND type='c'");
		while($forum = $db->fetch_array($query))
		{
			$forums[$forum['import_fid']] = $forum['fid'];
		}
		$this->cache_fids_c = $forums;
		$db->free_result($query);

		return $forums;
	}

	/**
	 * Get the MyBB FID of an old FID. (e.x. vBulletin category id [categories only, not forums])
	 *
	 * @param int Forum ID used before import
	 * @return int Forum ID in MyBB
	 */
	function fid_c($old_fid)
	{
		if(!is_array($this->cache_fids_c))
		{
			$this->cache_forums_c();
		}

		return $this->cache_fids_c[$old_fid];
	}

	/**
	 * Get an array of imported threads (e.x. array vBulletin thread id => MyBB thread id)
	 *
	 * @return array
	 */
	function cache_threads()
	{
		global $db;

		$query = $db->simple_select("threads", "tid, import_tid", "import_tid>0");
		while($thread = $db->fetch_array($query))
		{
			$threads[$thread['import_tid']] = $thread['tid'];
		}
		$this->cache_tids = $threads;
		$db->free_result($query);

		return $threads;
	}

	/**
	 * Get the MyBB TID of an old TID. (e.x. vBulletin thread id)
	 *
	 * @param int Thread ID used before import
	 * @return int Thread ID in MyBB
	 */
	function tid($old_tid)
	{
		if(!is_array($this->cache_tids))
		{
			$this->cache_threads();
		}

		return $this->cache_tids[$old_tid];
	}

	/**
	 * Get an array of imported usergroups (e.x. array vBulletin user group id => MyBB user group id)
	 *
	 * @return array
	 */
	function cache_usergroups()
	{
		global $db;

		$query = $db->simple_select("usergroups", "gid, import_gid", "import_gid>0");
		while($usergroup = $db->fetch_array($query))
		{
			$usergroups[$usergroup['import_gid']] = $usergroup['gid'];
		}
		$this->cache_gids = $usergroups;
		$db->free_result($query);

		return $usergroups;
	}

	/**
	 * Get the MyBB usergroup ID of an old GID. (e.x. vBulletin usergroup id)
	 *
	 * @param int Group ID used before import
	 * @return int Group ID in MyBB
	 */
	function gid($old_gid)
	{
		if(!is_array($this->cache_gids))
		{
			$this->cache_usergroups();
		}

		return $this->cache_gids[$old_gid];
	}

	/**
	 * Get an array of imported attachments (e.x. array vBulletin attachment id => MyBB attachment id)
	 *
	 * @return array
	 */
	function cache_attachments()
	{
		global $db;

		$query = $db->simple_select("attachments", "aid, import_aid", "import_aid>0");
		while($attachment = $db->fetch_array($query))
		{
			$attachments[$attachment['import_aid']] = $attachment['aid'];
		}
		$this->cache_attachments = $attachments;
		$db->free_result($query);

		return $attachments;
	}

	/**
	 * Get the MyBB attachments ID of an old AID. (e.x. vBulletin attachment id)
	 *
	 * @param int Attachment ID used before import
	 * @return int Attachment ID in MyBB
	 */
	function aid($old_aid)
	{
		if(!is_array($this->cache_attachments))
		{
			$this->cache_attachments();
		}

		return $this->cache_attachments[$old_aid];
	}

	/**
	 * Get an array of imported events (e.x. array vBulletin event id => MyBB event id)
	 *
	 * @return array
	 */
	function cache_events()
	{
		global $db;

		$query = $db->simple_select("events", "eid, import_eid", "import_eid>0");
		while($event = $db->fetch_array($query))
		{
			$events[$event['import_eid']] = $event['eid'];
		}
		$this->cache_eids = $events;
		$db->free_result($query);

		return $events;
	}

	/**
	 * Get the MyBB event ID of an old EID. (e.x. vBulletin event id)
	 *
	 * @param int Event ID used before import
	 * @return int Event ID in MyBB
	 */
	function eid($old_eid)
	{
		if(!is_array($this->cache_events))
		{
			$this->cache_events();
		}

		return $this->cache_events[$old_eid];
	}

	/**
	 * Get an array of imported posts (e.x. array vBulletin post id => MyBB post id)
	 *
	 * @return array
	 */
	function cache_posts()
	{
		global $db;

		if(!$db->table_exists("post_trackers"))
		{
			return false;
		}

		$query = $db->simple_select("post_trackers", "pid, import_pid");
		while($post = $db->fetch_array($query))
		{
			$posts[$post['import_pid']] = $post['pid'];
		}
		$this->cache_posts = $posts;
		$db->free_result($query);

		return $posts;
	}

	/**
	 * Get the MyBB post ID of an old PID. (e.x. vBulletin post id)
	 *
	 * @param int Post ID used before import
	 * @return int Post ID in MyBB
	 */
	function pid($old_pid)
	{
		if(!is_array($this->cache_posts))
		{
			$this->cache_posts();
		}

		return $this->cache_posts[$old_pid];
	}
}

?>