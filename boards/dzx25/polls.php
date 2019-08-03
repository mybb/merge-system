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

class DZX25_Converter_Module_Polls extends Converter_Module_Polls {
	
	var $settings = array(
			'friendly_name' => 'polls',
			'progress_column' => 'tid',
			'default_per_screen' => 1000,
	);
	
	var $cache_poll_choices = array();
	
	function import()
	{
		global $import_session, $db;

		$query = $this->old_db->query("
			SELECT
				poll.*,
				thread.subject AS subject,
				thread.dateline AS dateline
			FROM ".OLD_TABLE_PREFIX."forum_poll AS poll
				LEFT JOIN ".OLD_TABLE_PREFIX."forum_thread AS thread
					ON (thread.tid = poll.tid)
			ORDER BY poll.tid ASC
			LIMIT ".$this->trackers['start_polls'].", ".$import_session['polls_per_screen']
		);
		while($poll = $this->old_db->fetch_array($query))
		{
			$pid = $this->insert($poll);
			
			// Restore connections
			$db->update_query("threads", array('poll' => $pid), "import_poll = '".$poll['tid']."'");
		}
	}
	
	function convert_data($data)
	{
		$insert_data = array();
		
		// Discuz! values
		$insert_data['import_tid'] = $data['tid'];
		$insert_data['import_pid'] = $data['tid'];
		$insert_data['tid'] = $this->get_import->tid($data['tid']);
		$insert_data['question'] = $this->board->encode_to_utf8($data['subject'], "forum_thread", "threads");
		$insert_data['dateline'] = $data['dateline'];
		
		$poll_choices = $this->get_poll_choices($data['tid']);
		$insert_data['options'] = $poll_choices['options'];
		$insert_data['votes'] = $poll_choices['votes'];
		$insert_data['numoptions'] = $poll_choices['options_count'];
		$insert_data['numvotes'] = $poll_choices['vote_count'];
		
		if(!empty($data['expiration']))
		{
			$days = (int) $data['expiration'] - (int) $data['dateline'];
			if($days > 0)
			{
				$days = $days / 86400;
				$insert_data['timeout'] = $days > 0 ? $days : 1;
			}
		}
		$insert_data['multiple'] = $data['multiple'];
		$insert_data['public'] = $data['visible'];
		
		if($data['multiple'] == 0)
		{
			$insert_data['maxoptions'] = 0;
		}
		else
		{
			$insert_data['maxoptions'] = $data['maxchoices'];
		}
		
		return $insert_data;
	}
	
	function get_poll_choices($tid)
	{
		if(array_key_exists($tid, $this->cache_poll_choices))
		{
			return $this->cache_poll_choices[$tid];
		}
		
		$seperator = '';
		$options = '';
		$votes = '';
		$vote_count = 0;
		$options_count = 0;

		// Use a complex query to maintain poll options display order
		$query = $this->old_db->query("
			SELECT
				*
			FROM ".OLD_TABLE_PREFIX."forum_polloption
			WHERE tid = '".$tid."'
			ORDER BY displayorder ASC, polloptionid ASC
		");
		while($vote_result = $this->old_db->fetch_array($query))
		{
			$vote_option = $this->board->encode_to_utf8($vote_result['polloption'], "forum_polloption", "threads");
			$options .= $seperator.$this->old_db->escape_string($vote_option);
			$votes .= $seperator.$vote_result['votes'];
			++$options_count;
			$vote_count += $vote_result['votes'];
			$seperator = '||~|~||';
		}
		$this->old_db->free_result($query);
		
		$poll_choices = array(
				'options' => $options,
				'votes' => $votes,
				'options_count' => $options_count,
				'vote_count' => $vote_count
		);
		
		$this->cache_poll_choices[$tid] = $poll_choices;
		
		return $poll_choices;
	}
	
	function fetch_total()
	{
		global $import_session;
		
		// Get number of polls
		if(!isset($import_session['total_polls']))
		{
			$query = $this->old_db->simple_select("forum_poll", "COUNT(*) as count");
			$import_session['total_polls'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}
		
		return $import_session['total_polls'];
	}
}


