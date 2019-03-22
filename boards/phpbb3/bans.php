<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Author: Brad Veryard (https://github.com/veryard)
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 */

if(!defined('IN_MYBB')) {
    die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class PHPBB3_Converter_Module_Bans extends Converted_Module_Bans
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

        while($ban = $this->old_db->fetch_array($query)) {
            $this->insert($ban);
        }
    }

    function convert_data($data)
    {
        $insert_data = array();

        $insert_data['uid'] = $this->get_import->uid($data['ban_userid']);
        $insert_data['gid'] = '';
        $insert_data['dateline'] = $data['ban_start'];
        $insert_data['lifted']  = $data['ban_end'];
        $insert_data['reason']  = $data['ban_reason'];

        return $insert_data;
    }

    function fetch_total()
    {
        global $import_session;

        if(!isset($import_session['total_bans'])) {
            $query = $this->old_db->simple_select('banlist', 'COUNT(*) as count');
            $import_session['total_bans'] = $this->old_db->fetch_field($query, 'count');
            $this->old_db->free_result($query);
        }

        return $import_session['total_bans'];
    }
}