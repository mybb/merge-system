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

class DZX25_Converter_Module_Userfields extends Converter_Module {
	
	var $settings = array(
			'friendly_name' => 'userfields',
			'progress_column' => 'uid',
			'encode_table' => 'common_member_profile',
			'default_per_screen' => 2000,
	);
	
	var $ufid_found = false;
	
	function import()
	{
		global $import_session;
		
		$query = $this->old_db->query("
			SELECT
				member.uid AS uid,
				member.credits AS credits,
				membercount.digestposts AS digestposts,
				membercount.extcredits1 AS extcredits1,
				membercount.extcredits2 AS extcredits2,
				membercount.extcredits3 AS extcredits3,
				membercount.extcredits4 AS extcredits4,
				membercount.extcredits5 AS extcredits5,
				membercount.extcredits6 AS extcredits6,
				membercount.extcredits7 AS extcredits7,
				membercount.extcredits8 AS extcredits8,
				memberprofile.address AS address,
				memberprofile.bio AS bio,
				memberprofile.gender AS gender,
				memberprofile.qq AS qq,
				memberforum.medals AS medals
			FROM ".OLD_TABLE_PREFIX."common_member AS member
				LEFT JOIN ".OLD_TABLE_PREFIX."common_member_count AS membercount
					ON (membercount.uid = member.uid)
				LEFT JOIN ".OLD_TABLE_PREFIX."common_member_profile AS memberprofile
					ON (memberprofile.uid = member.uid)
				LEFT JOIN ".OLD_TABLE_PREFIX."common_member_field_forum AS memberforum
					ON (memberforum.uid = member.uid)
			ORDER BY memberforum.uid ASC
			LIMIT ".$this->trackers['start_userfields'].", ".$import_session['userfields_per_screen']
				);
		while($userfield = $this->old_db->fetch_array($query))
		{
			$this->insert($userfield);
		}
	}

	/**
	 * Customized user profilefield insertion into database, in order to use the system's internal tracker.
	 *
	 * @param array $data The insert array going into the MyBB database
	 * @return int|bool The new id or false if it's a duplicated user
	 */
	public function insert($data)
	{
		global $db, $output;
		
		$this->debug->log->datatrace('$data', $data);
		
		$output->print_progress("start", $data[$this->settings['progress_column']]);
		
		// Call our currently module's process function
		$data = $this->convert_data($data);
		
		// Should loop through and fill in any values that aren't set based on the MyBB db schema or other standard default values and escape them properly
		$insert_array = $this->prepare_insert_array($data, 'userfields');
		
		$this->debug->log->datatrace('$insert_array', $insert_array);
		
		$ufid = 0;
		if($this->ufid_found)
		{
			// Storing the mybbufid of a userfield to be updated.
			$ufid = $insert_array['ufid'];
		}
		if(isset($insert_array['ufid']))
		{
			unset($insert_array['ufid']);
		}
		
		if($this->ufid_found)
		{
			// Update a ufid record.
			if(defined("DXZ25_CONVERTER_USERS_PROFILE_OVERWRITE") && DXZ25_CONVERTER_USERS_PROFILE_OVERWRITE)
			{
				$db->update_query("userfields", $insert_array, "ufid = '{$ufid}'");
			}
			$this->ufid_found = false;
		}
		else
		{
			// Insert a new record.
			$db->insert_query("userfields", $insert_array);
			$ufid = $db->insert_id();
		}
		
		$this->increment_tracker('userfields');
		
		$output->print_progress("end");
		
		return $ufid;
	}
	
	function convert_data($data)
	{
		global $import_session, $DZ_USER_PROFILEFIELDS;
		
		$insert_data = array();
		
		$uid = $this->get_import->uid($data['uid']);
		
		// Check existing user profile field record.
		$this->ufid_found = $this->check_existing_record($uid);
		if($this->ufid_found !== false)
		{
			$this->ufid_found = true;
		}
		
		// Discuz! values
		$insert_data['ufid'] = $uid;
		
		foreach($DZ_USER_PROFILEFIELDS as $profilefield)
		{
			if($profilefield['fid'] != -1)
			{
				if($profilefield['fid'] != 0)
				{
					// MyBB predefined user profile field.
					$column = 'fid'.$profilefield['fid'];
					
					if($profilefield['name'] == 'location')
					{
						$insert_data[$column] = encode_to_utf8($data['address'], $this->settings['encode_table'], "userfields");
					}
					if($profilefield['name'] == 'bio')
					{
						$insert_data[$column] = encode_to_utf8($this->bbcode_parser->convert(utf8_unhtmlentities($data['bio'])), $this->settings['encode_table'], "userfields");
					}
					if($profilefield['name'] == 'sex')
					{
						if($data['gender'] == 0)
						{
							$insert_data[$column] = 'Undisclosed';
						}
						if($data['gender'] == 1)
						{
							$insert_data[$column] = 'Male';
						}
						if($data['gender'] == 2)
						{
							$insert_data[$column] = 'Female';
						}
						else
						{
							$insert_data[$column] = 'Undisclosed';
						}
					}
				}
				else
				{
					// No MyBB predefined user profile field, use import_profilefields first.
					if(!isset($import_session['dz_userprofilefields']) || empty($import_session['dz_userprofilefields']))
					{
						$this->debug->log->warning('$import_session[\'dz_userprofilefields\'] is empty or not set. We can\'t import this profile field for current user. '."uid => {$uid}, field => {$profilefield['name']}");
						continue;
					}
					
					if($profilefield['name'] == 'extcredits')
					{
						$profilefields_ids = array();
						foreach(array_keys($import_session['dz_userprofilefields']) as $key)
						{
							$name_id = explode('$##$', $key);
							if(!empty($name_id) && count($name_id) == 2 && $name_id[0] == $profilefield['name'])
							{
								$profilefields_ids[] = $name_id[1];
							}
						}
						if(empty($profilefields_ids))
						{
							$this->debug->log->warning('$import_session[\'dz_userprofilefields\'] is corrupt. We can\'t import this profile field for current user. '."uid => {$uid}, field => {$profilefield['name']}");
							continue;
						}
						
						foreach($profilefields_ids as $id)
						{
							if(!array_key_exists($profilefield['name'].$id, $data))
							{
								$this->debug->log->warning('We didn\'t fetch this field information, so can\'t import this profile field for current user. '."uid => {$uid}, field => {$profilefield['name']}, id => #{$id}");
								continue;
							}
							else
							{
								$column = 'fid'.$import_session['dz_userprofilefields'][$profilefield['name'].'$##$'.$id];
								$insert_data[$column] = $data[$profilefield['name'].$id];
								
							}
						}
					}
					else if($profilefield['name'] == 'medals')
					{
						$profilefields_ids = array();
						foreach(array_keys($import_session['dz_userprofilefields']) as $key)
						{
							$name_id = explode('$##$', $key);
							if(!empty($name_id) && count($name_id) == 2 && $name_id[0] == $profilefield['name'])
							{
								$profilefields_ids[] = $name_id[1];
							}
						}
						if(empty($profilefields_ids))
						{
							$this->debug->log->warning('$import_session[\'dz_userprofilefields\'] is corrupt. We can\'t import this profile field for current user. '."uid => {$uid}, field => {$profilefield['name']}");
							continue;
						}
						
						$medals = explode("\t", $data[$profilefield['name']]);
						
						foreach($medals as $medal)
						{
							if(empty($medal))
							{
								continue;
							}
							if(array_search($medal, $profilefields_ids) === false)
							{
								$this->debug->log->warning('We didn\'t have this field information, so can\'t import this profile field for current user. '."uid => {$uid}, field => {$profilefield['name']}, id => #{$medal}");
								continue;
							}
							else
							{
								$column = 'fid'.$import_session['dz_userprofilefields'][$profilefield['name'].'$##$'.$medal];
								$insert_data[$column] = 'Owned';
								
							}
						}
					}
					else
					{
						if(!array_key_exists($profilefield['name'], $import_session['dz_userprofilefields']))
						{
							$this->debug->log->warning('$import_session[\'dz_userprofilefields\'] is corrupt. We can\'t import this profile field for current user. '."uid => {$uid}, field => {$profilefield['name']}");
							continue;
						}
						else if(!array_key_exists($profilefield['name'], $data))
						{
							$this->debug->log->warning('We didn\'t fetch this field information, so can\'t import this profile field for current user. '."uid => {$uid}, field => {$profilefield['name']}");
							continue;
						}
						else
						{
							$column = 'fid'.$import_session['dz_userprofilefields'][$profilefield['name']];
							$insert_data[$column] = $data[$profilefield['name']];
							
						}
					}
				}
			}
		}
		
		return $insert_data;
	}
	
	function fetch_total()
	{
		global $import_session, $DZ_USER_PROFILEFIELDS;
		
		// Get number of members
		if(!isset($import_session['total_userfields']))
		{
			$query = $this->old_db->simple_select("common_member", "COUNT(*) as count");
			$import_session['total_userfields'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}
		
		$profilefields_to_import = 0;
		$profilefields_builtin_to_import = 0;
		foreach($DZ_USER_PROFILEFIELDS as $profilefield)
		{
			if($profilefield['fid'] != -1)
			{
				++$profilefields_to_import;
				if($profilefield['fid'] != 0)
				{
					++$profilefields_builtin_to_import;
				}
			}
		}
		
		if($profilefields_to_import != 0)
		{
			if($profilefields_builtin_to_import == 0)
			{
				if(!isset($import_session['dz_userprofilefields']) || empty($import_session['dz_userprofilefields']))
				{
					$this->debug->log->warning('$import_session[\'dz_userprofilefields\'] is empty or not set. Consider this module has nothing to do.');
					$import_session['total_userfields'] = 0;
				}
			}
			// TODO: should check more?
		}
		else
		{
			$this->debug->log->warning('No user profilefield is selected to import. Consider this module has nothing to do.');
			$import_session['total_userfields'] = 0;
		}
		
		return $import_session['total_userfields'];
	}
	
	function pre_setup()
	{
		global $db, $DZ_USER_PROFILEFIELDS;
		
		$profilefields_builtin = array(
				'location' => array('name' => 'Location', 'fid' => -1),
				'bio' => array('name' => 'Bio', 'fid' => -1),
				'sex' => array('name' => 'Sex', 'fid' => -1),
				);
		
		// Get `fid` of location, bio and sex.
		$query = $db->simple_select("profilefields", "fid,name", "name IN('".implode("','", array_column(array_values($profilefields_builtin), 'name'))."')", array('limit' => count($profilefields_builtin)));
		while($result = $db->fetch_array($query))
		{
			$result_name = dz_my_strtolower($result['name']);
			if(array_key_exists($result_name, $profilefields_builtin))
			{
				$profilefields_builtin[$result_name]['fid'] = $result['fid'];
			}
		}
		$db->free_result($query);
		
		// If this MyBB doesn't has any of the predefined profilefield, do not import it by setting `fid` in $DZ_USER_PROFILEFIELDS to -1.
		// And also make the fid number correct, this will make sure the corresponding `userfields` column exists. 
		for($i = 0; $i < count($profilefields_builtin); $i++)
		{
			foreach($profilefields_builtin as $profilefield_builtin_name => $profilefield_builtin_db_value)
			{
				if($DZ_USER_PROFILEFIELDS[$i]['name'] == $profilefield_builtin_name)
				{
					$DZ_USER_PROFILEFIELDS[$i]['fid'] = $profilefield_builtin_db_value['fid'];
					break;
				}
			}
		}
	}
	
	/**
	 * Check if $uid user has already got his userfield record.
	 *
	 * @param int $uid The uid of a user in MyBB
	 * @return int|bool The ufid of this user id or false if the record is not found in table `userfields`
	 */
	function check_existing_record($uid)
	{
		global $db;
		
		$query = $db->simple_select("userfields", "ufid", "ufid = {$uid}", array(limit => 1));
		$ufid = $db->fetch_field($query, "ufid");
		$db->free_result($query);
		
		if((!empty($ufid) || $ufid) && $uid == $ufid)
		{
			return $ufid;
		}
		else
		{
			return false;
		}
		
	}
}


