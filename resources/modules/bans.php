<?php
/**
 * MyBB 1.8 Merge System
 * Copyright 2019 MyBB Group, All Rights Reserved
 *
 * Author: Brad Veryard (https://github.com/veryard)
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/download/merge-system/license/
 */

abstract class Converter_Module_Bans extends Converter_Module
{
    public $default_values = array(
        'uid' => 0,
        'gid' => 0,
        'oldgroup' => '',
        'oldadditionalgroups' => '',
        'olddisplaygroup' => 0,
        'admin' => 0,
        'dateline' => 0,
        'bantime' => '',
        'lifted' => 0,
        'reason' => ''
    );

    public $integer_fields = array(
        'uid',
        'gid',
        'oldgroup',
        'olddisplaygroup',
        'admin',
        'dateline',
        'lifed'
    );

    public function insert($data)
    {
        global $db, $output;

        $this->debug->log->datatrace('$data', $data);

        $output->print_progress("start", $data[$this->settings['progress_column']]);

        $data  = $this->convert_data($data);

        $insert_array = $this->prepare_insert_array($data, 'banned');

        $this->debug->log->datatrace('$insert_array', $insert_array);

        $db->insert_query('banned', $insert_array);

        $this->increment_tracker('bans');

        $output->print_progress("end");

        return true;
    }
}