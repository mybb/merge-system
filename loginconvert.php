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

$plugins->add_hook("datahandler_login_validate_start", "loginconvert_convert", 1);

global $valid_login_types;
$valid_login_types = array(
	"vb3"		=> "vb",		// Module isn't supported anymore, but old merges may require it
	"vb4"		=> "vb",
	"vb5"		=> "vb",		// Not yet supported but as vb doesn't change their hashing...
	"ipb2"		=> "ipb",		// Module isn't supported anymore, but old merges may require it
	"ipb3"		=> "ipb",
	"ipb4"		=> "ipb4",
	"smf"		=> "smf",		// Isn't supported anymore, but the function is still required by smf 1.1 and 2 and there may be "old" users
	"smf11"		=> "smf11",
	"smf2"		=> "smf2",
	"punbb"		=> "punbb",
	"phpbb3"	=> "phpbb3",
	"bbpress"	=> "bbpress",
	"xf"		=> "xf11",		// XenForo can have two different authentications
	"xf12"		=> "xf12",		// 1.2+ use PHP's crypt function by default
	"wbb3"		=> "wcf1",		// WBB 3 and Lite 2 use WoltLab Community Framework 1.x with some special parameters
	"wbb4"		=> "wcf2",		// WBB 4 uses WoltLab Community Framework 2.x
	"vanilla"	=> "vanilla",
	"fluxbb"	=> "punbb",		// FluxBB is a fork of PunBB and they didn't change the hashing part
);

function loginconvert_info()
{
	global $db;

	$info = array(
		"name"				=> "Login Password Conversion",
		"description"		=> "Converts passwords to the correct type when logging in. To be used in conjunction with the MyBB Merge System.",
		"website"			=> "http://www.mybb.com",
		"author"			=> "MyBB Group",
		"authorsite"		=> "http://www.mybb.com",
		"version"			=> "1.4",
		"guid"				=> "",
		"compatibility"		=> "18*",
	);

	if($db->field_exists("passwordconvert", "users"))
	{
		// Checks whether the plugin is really needed
		$query = $db->simple_select("users", "uid", "passwordconvert IS NOT NULL AND passwordconvert!=''", array("limit" => 1));
		if($db->num_rows($query) > 0)
		{
			$info['description'] .= "<br />This plugin should be activated as there are users with unconverted password.";
		}
		else
		{
			$info['description'] .= "<br />This plugin can be deactivated and deleted as all passwords are converted.";
		}
	}
	else
	{
		$info['description'] .= "<br />Please delete the file \"inc/plugins/loginconvert.php\"";
	}

	return $info;
}

function loginconvert_activate()
{
	global $db;

	// Don't activate the plugin if it isn't needed
	if(!$db->field_exists("passwordconvert", "users"))
	{
		flash_message("There's no need to activate this plugin as there aren't any passwords which need to be converted", "error");
		admin_redirect("index.php?module=config-plugins");
	}
}

function loginconvert_deactivate()
{
	global $db;

	// Remove the columns if all passwords have been converted
	$query = $db->simple_select("users", "uid", "passwordconvert IS NOT NULL AND passwordconvert!=''", array("limit" => 1));
	if($db->num_rows($query) == 0)
	{
		$db->drop_column("users", "passwordconvert");
		$db->drop_column("users", "passwordconverttype");
		$db->drop_column("users", "passwordconvertsalt");
	}
}

function loginconvert_convert(&$login)
{
	global $mybb, $valid_login_types, $db, $settings;

	$options = array(
		"fields" => array('username', "password", "salt", 'loginkey', 'coppauser', 'usergroup', "passwordconvert", "passwordconverttype", "passwordconvertsalt"),
		"username_method" => (int)$settings['username_method']
	);

	if($login->username_method !== null)
	{
		$options['username_method'] = (int)$login->username_method;
	}

	$user = get_user_by_username($login->data['username'], $options);

	// There's nothing to check for, let MyBB do everything
	// This fails also when no user was found above, so no need for an extra check
	if(!isset($user['passwordconvert']) || $user['passwordconvert'] == '')
	{
		return;
	}

	if(!array_key_exists($user['passwordconverttype'], $valid_login_types))
	{
		// TODO: Is there an easy way to make the error translatable without adding a new language file?
		redirect($mybb->settings['bburl']."/member.php?action=lostpw", "We're sorry but we couldn't convert your old password. Please select a new one", "", true);
	}
	else
	{
		$function = "check_".$valid_login_types[$user['passwordconverttype']];
		$check = $function($login->data['password'], $user);

		if(!$check)
		{
			// Yeah, that function is called later too, but we need to know whether the captcha is right
			// If we wouldn't call that function the error would always be shown
			$login->verify_attempts($mybb->settings['captchaimage']);

			$login->invalid_combination(true);
		}
		else
		{
			// The password was correct, so use MyBB's method the next time (even if the captcha was wrong we can update the password)
			$salt = generate_salt();
			$update = array(
				"salt"					=> $salt,
				"password"				=> salt_password(md5($login->data['password']), $salt),
				"loginkey"				=> generate_loginkey(),
				"passwordconverttype"	=> "",
				"passwordconvert"		=> "",
				"passwordconvertsalt"	=> "",
			);

			$db->update_query("users", $update, "uid='{$user['uid']}'");

			// Make sure the password isn't tested again
			unset($login->data['password']);

			// Also make sure all data is available when creating the session (otherwise SQL errors -.-)
			$login->login_data = array_merge($user, $update);
		}
	}
}

// Password functions

function check_vb($password, $user)
{
	if(md5(md5($password).$user['passwordconvertsalt']) == $user['passwordconvert'] || md5($password.$user['passwordconvertsalt']) == $user['passwordconvert'])
	{
		return true;
	}

	return false;
}

function check_ipb($password, $user)
{
	// The salt was saved in the "salt" column on IPB 2 but we changed it in IPB 3 to use the correct "passwordconvertsalt" column
	if(!empty($user['passwordconvertsalt']))
	{
		$salt = $user['passwordconvertsalt'];
	}
	else if(!empty($user['salt']))
	{
		$salt = $user['salt'];
	}
	else
	{
		return false;
	}

	if($user['passwordconvert'] == md5(md5($salt).md5($password)))
	{
		return true;
	}

	return false;
}

function check_ipb4($password, $user)
{
	if($user['passwordconvert'] == crypt($password, '$2a$13$'.$user['passwordconvertsalt']))
	{
		return true;
	}

	return false;
}

function check_smf($password, $user)
{
	if(crypt($password, substr($password, 0, 2)) == $user['passwordconvert'])
	{
		return true;
	}
	else if(my_strlen($user['passwordconvert']) == 32 && md5_hmac(preg_replace("#\_smf1\.1\_import(\d+)$#i", '', $user['username']), $password) == $user['passwordconvert'])
	{
		return true;
	}
	else if(my_strlen($user['passwordconvert']) == 32 && md5($password) == $user['passwordconvert'])
	{
		return true;
	}

	return false;
}

function check_smf11($password, $user)
{
	if(my_strlen($user['passwordconvert']) == 40)
	{
		$is_sha1 = true;
	}
	else
	{
		$is_sha1 = false;
	}

	if($is_sha1 && sha1(strtolower(preg_replace("#\_smf1\.1\_import(\d+)$#i", '', $user['username'])).$password) == $user['passwordconvert'])
	{
		return true;
	}
	else
	{
		return check_smf($password, $user);
	}

	return false;
}

function check_smf2($password, $user)
{
	if(my_strlen($user['passwordconvert']) == 40)
	{
		$is_sha1 = true;
	}
	else
	{
		$is_sha1 = false;
	}

	if($is_sha1 && sha1(strtolower(preg_replace("#\_smf2\.0\_import(\d+)$#i", '', $user['username'])).$password) == $user['passwordconvert'])
	{
		return true;
	}
	else
	{
		return check_smf($password, $user);
	}

	return false;
}

function check_punbb($password, $user)
{
	if(my_strlen($user['passwordconvert']) == 40)
	{
		$is_sha1 = true;
	}
	else
	{
		$is_sha1 = false;
	}

	if(function_exists('sha1') && $is_sha1 && (sha1($password) == $user['passwordconvert'] || sha1($user['passwordconvertsalt'].sha1($password)) == $user['passwordconvert']))
	{
		return true;
	}
	elseif(function_exists('mhash') && $is_sha1 && (bin2hex(mhash(MHASH_SHA1, $password)) == $user['passwordconvert'] || bin2hex(mhash(MHASH_SHA1, $user['passwordconvertsalt'].bin2hex(mhash(MHASH_SHA1, $password)))) == $user['passwordconvert']))
	{
		return true;
	}
	else if(md5($password) == $user['passwordconvert'])
	{
		return true;
	}

	return false;
}

function check_phpbb3($password, $user)
{
	if (my_strlen($user['passwordconvert']) == 34)
	{
		if(phpbb3_crypt_private($password, $user['passwordconvert']) === $user['passwordconvert'])
		{
			return true;
		}

		return false;
	}

	if(md5($user['passwordconvert']) === $hash)
	{
		return true;
	}
	return false;
}

function check_bbpress($password, $user)
{
	// WordPress (and so bbPress) used simple md5 hashing some time ago
	if ( strlen($user['passwordconvert']) <= 32 )
	{
		return ($hash == md5($password));
	}
	else
	{
		$hash = bbpress_crypt_private($password, $user['passwordconvert']);
		if ($hash[0] == '*')
		{
			$hash = crypt($password, $user['passwordconvert']);
		}

		return $hash === $user['passwordconvert'];
	}
}

function check_xf11($password, $user)
{
	$hash = xf_hash(xf_hash($password));
	return ($hash === $user['passwordconvert']);
}

function check_xf12($password, $user)
{
	if ($user['passwordconvert'] == crypt($password, $user['passwordconvert']))
	{
		return true;
	}

	return false;
}

function check_wcf1($password, $user)
{
	// WCF 1 has some special parameters, which are saved in the passwordconvert field
	$settings = my_unserialize($user['passwordconvert']);
	$user['passwordconvert'] = $settings['password'];

	if(wcf1_encrypt($user['passwordconvertsalt'].wcf1_hash($password, $user['passwordconvertsalt'], $settings), $settings['encryption_method']) == $user['passwordconvert'])
	{
		return true;
	}

	return false;
}

function check_wcf2($password, $user)
{
	// WCF 2 doesn't save the salt in a seperate column and it's easier to fetch it when it's needed than doing it while merging
	$salt = mb_substr($user['passwordconvert'], 0, 29);

	return (crypt(crypt($password, $salt), $salt) == $user['passwordconvert']);
}

function check_vanilla($password, $user)
{
	if($user['passwordconvert'][0] === '_' || $user['passwordconvert'][0] === '$')
	{
		$hash = vanilla_crypt_private($password, $user['passwordconvert']);
		if ($hash[0] == '*')
		{
			$hash = crypt($password, $user['passwordconvert']);
		}

		return $hash == $user['passwordconvert'];
	}
	else if($password && $user['passwordconvert'] !== '*' && ($password === $user['passwordconvert'] || md5($password) === $user['passwordconvert']))
	{
		return true;
	}

	return false;
}

/************************************
 * Helpers used by different boards *
 ************************************/

// Used by WCF1
function wcf1_encrypt($value, $method) {
	switch ($method) {
		case 'sha1': return sha1($value);
		case 'md5': return md5($value);
		case 'crc32': return crc32($value);
		case 'crypt': return crypt($value);
	}
}

function wcf1_hash($value, $salt, $settings) {
	if ($settings['encryption_enable_salting']) {
		$hash = '';
		// salt
		if ($settings['encryption_salt_position'] == 'before') {
			$hash .= $salt;
		}

		// value
		if ($settings['encryption_encrypt_before_salting']) {
			$hash .= wcf1_encrypt($value, $settings['encryption_method']);
		}
		else {
			$hash .= $value;
		}

		// salt
		if ($settings['encryption_salt_position'] == 'after') {
			$hash .= $salt;
		}

		return wcf1_encrypt($hash, $settings['encryption_method']);
	}
	else {
		return wcf1_encrypt($value, $settings['encryption_method']);
	}
}


// Used by XenForo 1.0 and 1.1
function xf_hash($data)
{
	if (extension_loaded('hash'))
	{
		return hash('sha256', $data);
	}
	else
	{
		return sha1($data);
	}

}

// Used by SMF 1.0
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

/********************************************************
 * phpass functions - first the crypt_private functions *
 * then the encoding function used by all boards        *
 ********************************************************/

// Used by bbPress
function bbpress_crypt_private($password, $setting)
{
	$itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

	$output = '*0';
	if (substr($setting, 0, 2) == $output)
		$output = '*1';

	if (substr($setting, 0, 3) != '$P$')
		return $output;
	$count_log2 = strpos($itoa64, $setting[3]);
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

	$output = substr($setting, 0, 12);
	$output .= _hash_encode64($hash, 16, $itoa64);

	return $output;
}

// Used by phpBB 3
function phpbb3_crypt_private($password, $setting)
{
	$itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

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

// Used by Vanilla
function vanilla_crypt_private($password, $setting)
{
	$itoa64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

	$output = '*0';
	if (substr($setting, 0, 2) == $output)
		$output = '*1';

	if (substr($setting, 0, 3) != '$P$')
		return $output;

	$count_log2 = strpos($itoa64, $setting[3]);
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

	$output = substr($setting, 0, 12);
	$output .= _hash_encode64($hash, 16, $itoa64);

	return $output;
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