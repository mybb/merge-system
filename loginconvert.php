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

$plugins->add_hook("member_do_login_start", "loginconvert_convert", 1);

function loginconvert_info()
{
	return array(
		"name"				=> "Login Password Conversion",
		"description"		=> "Converts passwords to the correct type when logging in. To be used in conjunction with the MyBB Merge System.",
		"website"			=> "http://www.mybb.com",
		"author"			=> "MyBB Group",
		"authorsite"		=> "http://www.mybb.com",
		"version"			=> "1.4",
		"guid"				=> "",
		"compatibility"	=> "18*",
	);
}

function loginconvert_activate()
{
}

function loginconvert_deactivate()
{
}

function loginconvert_convert()
{
	global $mybb, $db, $lang, $session, $plugins, $inline_errors, $errors;

	if($mybb->input['action'] != "do_login" || $mybb->request_method != "post")
	{
		return;
	}

	// Checks to make sure the user can login; they haven't had too many tries at logging in.
	// Is a fatal call if user has had too many tries
	$logins = login_attempt_check();
	$login_text = '';

	// Did we come from the quick login form?
	if($mybb->input['quick_login'] == "1" && $mybb->input['quick_password'] && $mybb->input['quick_username'])
	{
		$mybb->input['password'] = $mybb->input['quick_password'];
		$mybb->input['username'] = $mybb->input['quick_username'];
	}

	if(!username_exists($mybb->input['username']))
	{
		my_setcookie('loginattempts', $logins + 1);
		error($lang->error_invalidpworusername.$login_text);
	}

	$query = $db->simple_select("users", "loginattempts", "LOWER(username)='".$db->escape_string(my_strtolower($mybb->input['username']))."'", array('limit' => 1));
	$loginattempts = $db->fetch_field($query, "loginattempts");

	$errors = array();

	$user = loginconvert_validate_password_from_username($mybb->input['username'], $mybb->input['password']);
	if(!$user['uid'])
	{
		my_setcookie('loginattempts', $logins + 1);
		$db->write_query("UPDATE ".TABLE_PREFIX."users SET loginattempts=loginattempts+1 WHERE LOWER(username) = '".$db->escape_string(my_strtolower($mybb->input['username']))."'");

		$mybb->input['action'] = "login";
		$mybb->input['request_method'] = "get";

		if($mybb->settings['failedlogintext'] == 1)
		{
			$login_text = $lang->sprintf($lang->failed_login_again, $mybb->settings['failedlogincount'] - $logins);
		}

		$errors[] = $lang->error_invalidpworusername.$login_text;
	}
	else
	{
		$correct = true;
	}

	if($loginattempts > 3 || intval($mybb->cookies['loginattempts']) > 3)
	{
		// Show captcha image for guests if enabled
		if($mybb->settings['captchaimage'] == 1 && function_exists("imagepng") && !$mybb->user['uid'])
		{
			// If previewing a post - check their current captcha input - if correct, hide the captcha input area
			if($mybb->input['imagestring'])
			{
				$imagehash = $db->escape_string($mybb->input['imagehash']);
				$imagestring = $db->escape_string($mybb->input['imagestring']);
				$query = $db->simple_select("captcha", "*", "imagehash='{$imagehash}' AND imagestring='{$imagestring}'");
				$imgcheck = $db->fetch_array($query);
				if($imgcheck['dateline'] > 0)
				{
					$correct = true;
				}
				else
				{
					$db->delete_query("captcha", "imagehash='{$imagehash}'");
					$errors[] = $lang->error_regimageinvalid;
				}
			}
			else if($mybb->input['quick_login'] == 1 && $mybb->input['quick_password'] && $mybb->input['quick_username'])
			{
				$errors[] = $lang->error_regimagerequired;
			}
			else
			{
				$errors[] = $lang->error_regimagerequired;
			}
		}

		$do_captcha = true;
	}

	if(!empty($errors))
	{
		$mybb->input['action'] = "login";
		$mybb->input['request_method'] = "get";

		$inline_errors = inline_error($errors);
	}
	else if($correct)
	{
		if($user['coppauser'])
		{
			error($lang->error_awaitingcoppa);
		}

		my_setcookie('loginattempts', 1);
		$ip_address = $db->escape_binary($session->packedip);
		$db->delete_query("sessions", "ip = {$ip_address} AND sid != '{$session->sid}'");
		$newsession = array(
			"uid" => $user['uid'],
		);
		$db->update_query("sessions", $newsession, "sid='".$session->sid."'");

		$db->update_query("users", array("loginattempts" => 1), "uid='{$user['uid']}'");

		my_setcookie("mybbuser", $user['uid']."_".$user['loginkey'], null, true);
		my_setcookie("sid", $session->sid, -1, true);

		$plugins->run_hooks("member_do_login_end");

		if($mybb->input['url'] != "" && my_strpos(basename($mybb->input['url']), 'member.php') === false)
		{
			if((my_strpos(basename($mybb->input['url']), 'newthread.php') !== false || my_strpos(basename($mybb->input['url']), 'newreply.php') !== false) && my_strpos($mybb->input['url'], '&processed=1') !== false)
			{
				$mybb->input['url'] = str_replace('&processed=1', '', $mybb->input['url']);
			}

			$mybb->input['url'] = str_replace('&amp;', '&', $mybb->input['url']);

			// Redirect to the URL if it is not member.php
			redirect(htmlentities($mybb->input['url']), $lang->redirect_loggedin);
		}
		else
		{
			redirect("index.php", $lang->redirect_loggedin);
		}
	}
	else
	{
		$mybb->input['action'] = "login";
		$mybb->input['request_method'] = "get";
	}
}

/**
 * Checks a password with a supplied username.
 *
 * @param string The username of the user.
 * @param string The md5()'ed password.
 * @return boolean|array False when no match, array with user info when match.
 */
function loginconvert_validate_password_from_username($username, $password)
{
	global $db;

	if($db->field_exists("passwordconvert", "users") && $db->field_exists("passwordconverttype", "users"))
	{
		$query = $db->simple_select("users", "uid,username,password,salt,loginkey,passwordconvert,passwordconvertsalt,passwordconverttype", "username='".$db->escape_string($username)."'", array('limit' => 1));
		$convert = true;
	}
	else
	{
		$query = $db->simple_select("users", "uid,username,password,salt,loginkey", "username='".$db->escape_string($username)."'", array('limit' => 1));
		$convert = false;
	}
	$user = $db->fetch_array($query);
	if(!$user['uid'])
	{
		return false;
	}
	else
	{
		return loginconvert_validate_password_from_uid($user['uid'], $password, $user, $convert);
	}
}

/**
 * Checks a password with a supplied uid.
 *
 * @param int The user id.
 * @param string The md5()'ed password.
 * @param string An optional user data array.
 * @param boolean Weather the password is converted from another forum system
 * @return boolean|array False when not valid, user data array when valid.
 */
function loginconvert_validate_password_from_uid($uid, $password, $user = array(), $converted=false)
{
	global $db, $mybb;

	if($mybb->user['uid'] == $uid)
	{
		$user = $mybb->user;
	}

	if(!isset($user['password']))
	{
		if($converted == true)
		{
			$query = $db->simple_select("users", "uid,username,password,salt,loginkey,passwordconvert,passwordconvertsalt,passwordconverttype", "uid='".intval($uid)."'", array('limit' => 1));
			$user = $db->fetch_array($query);
		}
		else
		{
			$query = $db->simple_select("users", "uid,username,password,salt,loginkey", "uid='".intval($uid)."'", array('limit' => 1));
			$user = $db->fetch_array($query);
		}
	}

	if(isset($user['passwordconvert']) && trim($user['passwordconvert']) != '' && trim($user['passwordconverttype']) != '' && trim($user['password']) == '')
	{
		$convert = new loginConvert($user);

		return $convert->login($user['passwordconverttype'], $uid, $password);
	}

	if(!$user['salt'] && trim($user['password']) != '')
	{
		// Generate a salt for this user and assume the password stored in db is a plain md5 password
		$user['salt'] = generate_salt();
		$user['password'] = salt_password($user['password'], $user['salt']);
		$sql_array = array(
			"salt" => $user['salt'],
			"password" => $user['password']
		);
		$db->update_query("users", $sql_array, "uid='".$user['uid']."'", 1);
	}

	if(!$user['loginkey'])
	{
		$user['loginkey'] = generate_loginkey();
		$sql_array = array(
			"loginkey" => $user['loginkey']
		);
		$db->update_query("users", $sql_array, "uid = ".$user['uid'], 1);
	}

	if(salt_password(md5($password), $user['salt']) == $user['password'])
	{
		return $user;
	}
	else
	{
		return false;
	}
}

/*
 * This class allows us to take the encryption algorithm used by the convertee bulletin board with the plain text password
 * the user just logged in with, and match it against the encrypted password stored in the passwordconvert column added by
 * the Merge System. If we have success then apply MyBB's encryption to the plain-text password.
 */
class loginConvert
{
	var $user;

	function loginConvert($user)
	{
		$user['passwordconvert'] = trim($user['passwordconvert']);
		$user['passwordconvertsalt'] = trim($user['passwordconvertsalt']);
		$user['passwordconverttype'] = trim($user['passwordconverttype']);
		$this->user = $user;
	}

    function login($type, $uid, $password)
    {
		global $db;

		$password = trim($password);
		$return = false;

        switch($type)
        {
            case 'vb3':
                $return = $this->authenticate_vb3($password);
                break;
            case 'ipb2':
                $return = $this->authenticate_ipb2($password);
                break;
            case 'smf11':
                $return = $this->authenticate_smf11($password);
                break;
			  case 'smf2':
                $return = $this->authenticate_smf2($password);
                break;
            case 'smf':
                $return = $this->authenticate_smf($password);
                break;
			case 'fluxbb':
			case 'punbb':
				$return = $this->authenticate_punbb($password);
				break;
			case 'phpbb3':
				$return = $this->authenticate_phpbb3($password);
				break;
			case 'bbpress':
				$return = $this->authenticate_bbpress($password);
				break;
			case 'mingle':
				$return = $this->authenticate_bbpress($password);
				break;
            default:
                return false;
        }

		if($return == true)
		{
			// Generate a salt for this user and assume the password stored in db is empty
			$user['salt'] = generate_salt();
			$this->user['salt'] = $user['salt'];
			$user['password'] = salt_password(md5($password), $user['salt']);
			$this->user['password'] = $user['password'];
			$user['loginkey'] = generate_loginkey();
			$this->user['loginkey'] = $user['loginkey'];
			$user['passwordconverttype'] = '';
			$this->user['passwordconverttype'] = '';
			$user['passwordconvert'] = '';
			$this->user['passwordconvert'] = '';
			$user['passwordconvertsalt'] = '';
			$this->user['passwordconvertsalt'] = '';

			$db->update_query("users", $user, "uid='{$uid}'", 1);

			return $this->user;
		}

		return false;
    }

	// Authentication for punBB
	function authenticate_punbb($password)
	{
		if(my_strlen($this->user['passwordconvert']) == 40)
		{
			$is_sha1 = true;
		}
		else
		{
			$is_sha1 = false;
		}

		if(function_exists('sha1') && $is_sha1 && (sha1($password) == $this->user['passwordconvert'] || sha1($this->user['passwordconvertsalt'].sha1($password)) == $this->user['passwordconvert']))
		{
			return true;
		}
		elseif(function_exists('mhash') && $is_sha1 && (bin2hex(mhash(MHASH_SHA1, $password)) == $this->user['passwordconvert'] || bin2hex(mhash(MHASH_SHA1, $this->user['passwordconvertsalt'].bin2hex(mhash(MHASH_SHA1, $password)))) == $this->user['passwordconvert']))
		{
			return true;
		}
		else if(md5($password) == $this->user['passwordconvert'])
		{
			return true;
		}

		return false;
	}

    // Authentication for vB3
    function authenticate_vb3($password)
    {
		if(md5(md5($password).$this->user['passwordconvertsalt']) == $this->user['passwordconvert'] || md5($password.$this->user['passwordconvertsalt']) == $this->user['passwordconvert'])
		{
			return true;
		}

		return false;
    }


    // Authentication for SMF 1.1
    function authenticate_smf11($password)
    {
		if(my_strlen($this->user['passwordconvert']) == 40)
		{
			$is_sha1 = true;
		}
		else
		{
			$is_sha1 = false;
		}

		if($is_sha1 && sha1(strtolower(preg_replace("#\_smf1\.1\_import(\d+)$#i", '', $this->user['username'])).$password) == $this->user['passwordconvert'])
		{
			return true;
		}
		else
		{
		   return $this->authenticate_smf($password);
		}

		return false;
    }

	// Authentication for SMF 2
    function authenticate_smf2($password)
    {
		if(my_strlen($this->user['passwordconvert']) == 40)
		{
			$is_sha1 = true;
		}
		else
		{
			$is_sha1 = false;
		}

		if($is_sha1 && sha1(strtolower(preg_replace("#\_smf2\.0\_import(\d+)$#i", '', $this->user['username'])).$password) == $this->user['passwordconvert'])
		{
			return true;
		}
		else
		{
		   return $this->authenticate_smf($password);
		}

		return false;
    }

    // Authentication for SMF
    function authenticate_smf($password)
    {
		if(crypt($password, substr($password, 0, 2)) == $this->user['passwordconvert'])
		{
			return true;
		}
		else if(my_strlen($this->user['passwordconvert']) == 32 && $this->md5_hmac(preg_replace("#\_smf1\.1\_import(\d+)$#i", '', $this->user['username']), $password) == $this->user['passwordconvert'])
		{
			return true;
		}
		else if(my_strlen($this->user['passwordconvert']) == 32 && md5($password) == $this->user['passwordconvert'])
		{
			return true;
		}

        return false;
    }

	function authenticate_phpbb3($password)
	{
		if(phpbb_check_hash($password, $this->user['passwordconvert']))
		{
			return true;
		}

		return false;
	}

	// TODO: Finish this!
	function authenticate_bbpress($password)
	{
		if(bbpress_crypt_private($password, $this->user['passwordconvert']))
		{
			return true;
		}
		return false;
	}

	// Authentication for IPB 2
	function authenticate_ipb2($password)
	{
		if($this->user['passwordconvert'] == md5(md5($this->user['salt']).md5($password)))
		{
			return true;
		}

		return false;
	}

   	function md5_hmac($username, $password)
	{
		if(my_strlen($username) > 64)
		{
			$username = pack('H*', md5($username));
		}
		$username = str_pad($username, 64, chr(0x00));

		$k_ipad = $username ^ str_repeat(chr(0x36), 64);
		$k_opad = $username ^ str_repeat(chr(0x5c), 64);

		return md5($k_opad.pack('H*', md5($k_ipad.$password)));
	}
}

/**
* The BELOW code falls under public domain, allowing its use in MyBB for this script
* and can be redistributed under the GNU General Public License.
*/

/**
*
* @version Version 0.1
*
* Portable PHP password hashing framework.
*
* Written by Solar Designer <solar at openwall.com> in 2004-2006 and placed in
* the public domain.
*
* There's absolutely no warranty.
*
* The homepage URL for this framework is:
*
*	http://www.openwall.com/phpass/
*
* Please be sure to update the Version line if you edit this file in any way.
* It is suggested that you leave the main version number intact, but indicate
* your project name (after the slash) and add your own revision information.
*
* Please do not change the "private" password hashing method implemented in
* here, thereby making your hashes incompatible.  However, if you must, please
* change the hash type identifier (the "$P$") to something different.
*
* Obviously, since this code is in the public domain, the above are not
* requirements (there can be none), but merely suggestions.
*
*/

/**
* Check for correct password
*/
function phpbb_check_hash($password, $hash)
{
	$itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
	if (my_strlen($hash) == 34)
	{
		return (_hash_crypt_private($password, $hash, $itoa64) === $hash) ? true : false;
	}

	return (md5($password) === $hash) ? true : false;
}

/**
* Encode hash
*/
function _hash_encode64($input, $count, &$itoa64)
{
	$output = '';
	$i = 0;

	do
	{
		$value = ord($input[$i++]);
		$output .= $itoa64[$value & 0x3f];

		if ($i < $count)
		{
			$value |= ord($input[$i]) << 8;
		}

		$output .= $itoa64[($value >> 6) & 0x3f];

		if ($i++ >= $count)
		{
			break;
		}

		if ($i < $count)
		{
			$value |= ord($input[$i]) << 16;
		}

		$output .= $itoa64[($value >> 12) & 0x3f];

		if ($i++ >= $count)
		{
			break;
		}

		$output .= $itoa64[($value >> 18) & 0x3f];
	}
	while ($i < $count);

	return $output;
}

/**
* The crypt function/replacement
*/
function _hash_crypt_private($password, $setting, &$itoa64)
{
	$output = '*';

	// Check for correct hash
	if (substr($setting, 0, 3) != '$H$')
	{
		return $output;
	}

	$count_log2 = strpos($itoa64, $setting[3]);

	if ($count_log2 < 7 || $count_log2 > 30)
	{
		return $output;
	}

	$count = 1 << $count_log2;
	$salt = substr($setting, 4, 8);

	if (my_strlen($salt) != 8)
	{
		return $output;
	}

	/**
	* We're kind of forced to use MD5 here since it's the only
	* cryptographic primitive available in all versions of PHP
	* currently in use.  To implement our own low-level crypto
	* in PHP would result in much worse performance and
	* consequently in lower iteration counts and hashes that are
	* quicker to crack (by non-PHP code).
	*/
	if (PHP_VERSION >= 5)
	{
		$hash = md5($salt . $password, true);
		do
		{
			$hash = md5($hash . $password, true);
		}
		while (--$count);
	}
	else
	{
		$hash = pack('H*', md5($salt . $password));
		do
		{
			$hash = pack('H*', md5($hash . $password));
		}
		while (--$count);
	}

	$output = substr($setting, 0, 12);
	$output .= _hash_encode64($hash, 16, $itoa64);

	return $output;
}

function bbpress_crypt_private($password, $setting)
{
	$output = '*0';
	if (substr($setting, 0, 2) == $output)
		$output = '*1';

	if (substr($setting, 0, 3) != '$P$')
		return $output;
	$count_log2 = strpos($this->itoa64, $setting[3]);
	if ($count_log2 < 7 || $count_log2 > 30)
		return $output;

	$count = 1 << $count_log2;

	$salt = substr($setting, 4, 8);
	if (strlen($salt) != 8)
		return $output;

	# We're kind of forced to use MD5 here since it's the only
	# cryptographic primitive available in all versions of PHP
	# currently in use.  To implement our own low-level crypto
	# in PHP would result in much worse performance and
	# consequently in lower iteration counts and hashes that are
	# quicker to crack (by non-PHP code).
	if (PHP_VERSION >= '5') {
		$hash = md5($salt . $password, TRUE);
		do {
			$hash = md5($hash . $password, TRUE);
		} while (--$count);
	} else {
		$hash = pack('H*', md5($salt . $password));
		do {
			$hash = pack('H*', md5($hash . $password));
		} while (--$count);
	}

	$itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
	$output = substr($setting, 0, 12);
	$output .= _hash_encode64($hash, 16, $itoa64);

	return $output;
}

?>
