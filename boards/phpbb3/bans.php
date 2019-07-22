<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2019 MyBB Group, All Rights Reserved
 *
 * Author: Brad Veryard (https://github.com/veryard)
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 */

if(!defined('IN_MYBB')) {
    die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class PHPBB3_Converter_Module_Bans extends Converter_Module_Bans
{
    public $settings = array(
        'friendly_name' => 'bans',
        'progress_column' => 'uid',
        'default_per_screen'    => 1000
    );

    function import()
    {
        global $import_session;
        $query = $this->old_db->simple_select('banlist', '*', '', array('limit_start' => $this->trackers['start_bans'], 'limit' => $import_session['bans_per_screen']));

        // Load normal bans
        $query = $this->old_db->query("
			SELECT *
			FROM ".OLD_TABLE_PREFIX."banlist
			WHERE ban_userid IN (SELECT user_id FROM ".OLD_TABLE_PREFIX."users)
			LIMIT {$this->trackers['start_bans']}, {$import_session['bans_per_screen']}
		");

        while($ban = $this->old_db->fetch_array($query)) {
            $this->insert($ban);
        }
    }

    function convert_data($data)
    {
        $insert_data = array();

        $insert_data['uid'] = $this->get_import->uid($data['ban_userid']);
        $insert_data['gid'] = 7;
        $insert_data['dateline'] = $data['ban_start'];
        $insert_data['bantime'] = $this->banTime($data['ban_start'], $data['ban_end']);
        $insert_data['lifted']  = $data['ban_end'];
        if(empty($data['ban_reason'])) {
            $data['ban_reason'] = $data['ban_give_reason'];
        }
        $insert_data['reason']  = $data['ban_reason'];

        return $insert_data;
    }

    function fetch_total()
    {
        global $import_session;

        if(!isset($import_session['total_bans'])) {
            $query = $this->old_db->query("
                SELECT *
                FROM ".OLD_TABLE_PREFIX."banlist
                WHERE ban_userid IN (SELECT user_id FROM ".OLD_TABLE_PREFIX."users) 
            ");
            $import_session['total_bans'] = $this->old_db->fetch_field($query, 'count');
            $this->old_db->free_result($query);
        }

        return $import_session['total_bans'];
    }

    function banTime($start, $end)
    {
        // phpBB perm ban
        if($end == 0) {
            return '---';
        }
        // Convert both to normal dates to calc difference for the bantime string
        $diff = abs($end - $start);
        // Years
        $years = floor($diff / (365*60*60*24));
        // Months
        $months = floor(($diff - $years * 365*60*60*24)
            / (30*60*60*24));
        // Days
        $days = floor(($diff - $years * 365*60*60*24 -
                $months*30*60*60*24)/ (60*60*24));

        // Must be a ban less than one day, make one day
        if($days == 0 && $months == 0 && $years == 0) {
            // phpBB has 30 min bans etc etc.
            return '1-0-0';
        }

        return $days . '-' . $months . '-' . $years;
    }
}