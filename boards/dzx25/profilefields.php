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

class DZX25_Converter_Module_Profilefields extends Converter_Module
{
	var $settings = array(
			'friendly_name' => 'profilefields',
			'progress_column' => '',
			'encode_table' => 'profilefields',
			'default_per_screen' => 1000,
	);
	
	public $default_values = array(
			'description' => 'Discuz! X2.5 imported profilefield.',
			'disporder' => 0,
			'regex' => '',
			'length' => 0,
			'maxlength' => 0,
			'required' => 0,
			'registration' => 0,
			'profile' => 0,
			'postbit' => 0,
			'viewableby' => '',
			'editableby' => '',
			'postnum' => 0,
			'allowhtml' => 0,
			'allowmycode' => 0,
			'allowsmilies' => 0,
			'allowimgcode' => 0,
			'allowvideocode' => 0,
	);
	
	public $binary_fields = array(
	);
	
	public $integer_fields = array(
			'disporder',
			'length',
			'maxlength',
			'required',
			'registration',
			'profile',
			'postbit',
			'postnum',
			'allowhtml',
			'allowmycode',
			'allowsmilies',
			'allowimgcode',
			'allowvideocode',
	);
	
	public $dz_extcredits = array();
	
	public $dz_medals = array();
	
	/**
	 * Insert user profilefield into database
	 *
	 * @param array $data The profilefield array going into the MyBB database
	 * @return int|bool The new id of profilefield
	 */
	public function insert($data)
	{
		global $db, $output;
		
		$this->debug->log->datatrace('$data', $data);
		
		$output->print_progress("start", $data['fieldkey']);
		
		$increment = $data['increment'];
		
		// Call our currently module's process function
		$data = $this->convert_data($data);
		
		// Should loop through and fill in any values that aren't set based on the MyBB db schema or other standard default values and escape them properly
		$insert_array = $this->prepare_insert_array($data, 'profilefields');
		
		$this->debug->log->datatrace('$insert_array', $insert_array);
		
		$db->insert_query("profilefields", $insert_array);
		$fid = $db->insert_id();
		
		if($increment)
		{
			$this->increment_tracker('profilefields');
		}
		
		$output->print_progress("end");
		
		return $fid;
	}
	
	function import()
	{
		global $import_session, $DZ_USER_PROFILEFIELDS;
		
		$idx = (int) $this->trackers['start_profilefields'];
		for($i = $idx; $i <= $idx + $import_session['profilefields_per_screen']; $i++)
		{
			if($i >= count($DZ_USER_PROFILEFIELDS))
			{
				break;
			}
			$profilefield = $DZ_USER_PROFILEFIELDS[$i];
			if($profilefield['fid'] == 0)
			{
				if(!isset($import_session['dz_userprofilefields']) || empty($import_session['dz_userprofilefields']))
				{
					$import_session['dz_userprofilefields'] = array();
				}
				if($profilefield['name'] == 'extcredits')
				{
					if(empty($this->dz_extcredits))
					{
						$query = $this->old_db->simple_select("common_setting", "skey,svalue", "skey = 'extcredits'");
						$result_query = $this->old_db->fetch_field($query, "svalue");
						$this->old_db->free_result($query);
						
						$result = dz_unserialize($result_query);
						
						$this->dz_extcredits = $result;
						foreach($result as $key => $value)
						{
							if(!empty($value['title']))
							{
								$this->dz_extcredits[$key]['title'] = encode_to_utf8($value['title'], $profilefield['old_def_table'], "profilefields");
							}
						}
						
					}
					$rows = count($this->dz_extcredits);
					$inserted = 0;
					foreach($this->dz_extcredits as $id => $value)
					{
						$insert_data = array(
								'fieldkey' => $profilefield['name'],
								'fieldvalue' => array_merge(
										array('id' => $id),
										$value),
								'increment' => false,
								);
						if(++$inserted == $rows)
						{
							$insert_data['increment'] = true;
						}
						$fid = $this->insert($insert_data);
						$import_session['dz_userprofilefields'][$profilefield['name'].'$##$'.$id] = $fid;
					}
				}
				else if($profilefield['name'] == 'medals')
				{
					if(empty($this->dz_medals))
					{
						$query = $this->old_db->simple_select("forum_medal", "medalid,name,description,available");
						while($medal = $this->old_db->fetch_array($query))
						{
							$medal_encode_name = encode_to_utf8($medal['name'], $profilefield['old_def_table'], "profilefields");
							$medal_encode_description = encode_to_utf8($medal['description'], $profilefield['old_def_table'], "profilefields");
							$this->dz_medals[] = array('id' => $medal['medalid'], 'name' => $medal_encode_name, 'description' => $medal_encode_description, 'available' => $medal['available']);
						}
						$this->old_db->free_result($query);
					}
					$rows = count($this->dz_medals);
					$inserted = 0;
					foreach($this->dz_medals as $value)
					{
						$insert_data = array(
								'fieldkey' => $profilefield['name'],
								'fieldvalue' => $value,
								'increment' => false,
						);
						if(++$inserted == $rows)
						{
							$insert_data['increment'] = true;
						}
						$fid = $this->insert($insert_data);
						$import_session['dz_userprofilefields'][$profilefield['name'].'$##$'.$value['id']] = $fid;
					}
				}
				else
				{
					$insert_data = array(
							'fieldkey' => $profilefield['name'],
							'fieldvalue' => $value,
							'increment' => true,
					);
					$fid = $this->insert($insert_data);
					$import_session['dz_userprofilefields'][$profilefield['name']] = $fid;
				}
			}
		}
	}
	
	function convert_data($data)
	{
		$insert_data = array();
	
		$profilefield = $data['fieldvalue'];
		
		// Discuz! values.
		if($data['fieldkey'] == 'credits')
		{
			$insert_data['name'] = "Discuz! Credits";
			$insert_data['type'] = "text";
			$insert_data['regex'] = '[0-9]*';
			$insert_data['required'] = 0;
			$insert_data['registration'] = 0;
			$insert_data['profile'] = 1;
			$insert_data['postbit'] = 0;
			$insert_data['viewableby'] = '-1';
			$insert_data['editableby'] = '';
		}
		if($data['fieldkey'] == 'extcredits' && !empty($profilefield))
		{
			$dz_extcredit_id = $profilefield['id'];
			$dz_extcredit_name = empty($profilefield['title']) ? '#'.$dz_extcredit_id : $profilefield['title'];
			$insert_data['name'] = "Discuz! ExtCredits: " . $dz_extcredit_name;
			$insert_data['type'] = "text";
			$insert_data['regex'] = '[0-9]*';
			$insert_data['required'] = 0;
			$insert_data['registration'] = 0;
			$insert_data['postbit'] = 0;
			if($profilefield['available'] == 1)
			{
				$insert_data['profile'] = 1;
				$insert_data['viewableby'] = '-1';
			}
			else
			{
				$insert_data['profile'] = 0;
				$insert_data['viewableby'] = '';
			}
			$insert_data['editableby'] = '';
		}
		if($data['fieldkey'] == 'digestposts')
		{
			$insert_data['name'] = "Discuz! Digest Posts";
			$insert_data['type'] = "text";
			$insert_data['regex'] = '[0-9]*';
			$insert_data['required'] = 0;
			$insert_data['registration'] = 0;
			$insert_data['profile'] = 1;
			$insert_data['postbit'] = 0;
			$insert_data['viewableby'] = '-1';
			$insert_data['editableby'] = '';
		}
		if($data['fieldkey'] == 'qq')
		{
			$insert_data['name'] = "QQ";
			$insert_data['type'] = "text";
			$insert_data['regex'] = '[1-9][0-9]*';
			$insert_data['maxlength'] = 11;
			$insert_data['required'] = 0;
			$insert_data['registration'] = 0;
		}
		if($data['fieldkey'] == 'medals' && !empty($profilefield))
		{
			$dz_medal_id = $profilefield['id'];
			$dz_medal_name = empty($profilefield['name']) ? '#'.$dz_medal_id : $profilefield['name'];
			$dz_medal_description = $profilefield['description'];
			$insert_data['name'] = "Discuz! Medals: " . $dz_medal_name;
			$insert_data['description'] = "Discuz! imported profilefield: " . $dz_medal_description;
			$insert_data['type'] = "checkbox";
			$insert_data['required'] = 0;
			$insert_data['registration'] = 0;
			$insert_data['postbit'] = 0;
			if($profilefield['available'] == 1)
			{
				$insert_data['profile'] = 1;
				$insert_data['viewableby'] = '-1';
			}
			else
			{
				$insert_data['profile'] = 0;
				$insert_data['viewableby'] = '';
			}
			$insert_data['editableby'] = '';
		}
		
		return $insert_data;
	}
	
	function fetch_total()
	{
		global $import_session, $DZ_USER_PROFILEFIELDS;
		
		// Get number of profilefields to import.
		if(!isset($import_session['total_profilefields']))
		{
			$import_session['total_profilefields'] = 0;
			foreach($DZ_USER_PROFILEFIELDS as $value)
			{
				if($value['fid'] == 0)
				{
					++$import_session['total_profilefields'];
				}
			}
		}
		
		return $import_session['total_profilefields'];
	}
	
	function finish()
	{
		global $db, $import_session;
		
		// ALTER `userfields` TABLE
		if(!empty($import_session['dz_userprofilefields']))
		{
			$fids = array_values($import_session['dz_userprofilefields']);
			sort($fids);
			$sql_query = "ALTER TABLE ".TABLE_PREFIX."userfields";
			foreach($fids as $fid)
			{
				$sql_query .= " ADD COLUMN fid{$fid} TEXT,";
			}
			$sql_query = rtrim($sql_query, ',');
			$db->write_query($sql_query);
		}
	}
	
	function dz_unserialize($str)
	{
		$result = unserialize($str);
		if($result === false)
		{
			$result = unserialize(stripslashes($str));
		}
		return $result;
	}
}


