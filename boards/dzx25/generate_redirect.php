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

class DZX25_Redirect_Generator
{
	public function __construct()
	{
		$this->redirect_file_name = get_redirect_file_name();
		$this->redirect_file_header = get_redirect_file_header();
		$this->redirect_file_footer = get_redirect_file_footer();
		$this->redirect_file_class_begin = get_redirect_file_class_begin();
		$this->redirect_file_class_end = get_redirect_file_class_end();
		$this->redirect_file_body = get_redirect_file_body();
	}
	
	public static $redirect_file_path = '';
	/**
	 * The filenames of each module's redirect file.
	 * @var array
	 */
	public $redirect_file_name = array();
	
	/**
	 * The redirect file's header section.
	 * @var string
	 */
	public $redirect_file_header = '';
	
	/**
	 * The redirect file's footer section.
	 * @var string
	 */
	public $redirect_file_footer = '';
	
	/**
	 * The beginning part of the redirect class in its redirect file.
	 * @var array
	 */
	public $redirect_file_class_begin = array();
	
	/**
	 * The ending part of the redirect class in its redirect file.
	 * @var array
	 */
	public $redirect_file_class_end = array();
	
	/**
	 * The remaining part of the redirect file.
	 * @var array
	 */
	public $redirect_file_body = array();
	
	/**
	 * Internal file handler.
	 * @var resource
	 */
	private $file_handle;
	private $file_open = false;
	private function is_file_good()
	{
		if($this->file_handle)
		{
			return true;
		}
		else
		{
			return false;
		}
	}
	
	private $counter = 0;
	
	/**
	 * The module.
	 * @var string
	 */
	public $module = '';
	
	/**
	 * Generate a redirect file.
	 * @param string $module The module whose redirect will be generated.
	 * @param int $total The total records converted by the converter.
	 */
	public function generate_file($module = "index", $total = 0)
	{
		if(!isset($this->redirect_file_name[$module]) || empty($this->redirect_file_name[$module]) || empty(DZX25_Redirect_Generator::$redirect_file_path))
		{
			echo "An error prevents creating the redirect file: no such module '{$module}'.<br />";
			return;
		}
		
		$redirect_path = DZX25_Redirect_Generator::$redirect_file_path;
		if(!is_dir($redirect_path))
		{
			if(mkdir($redirect_path, 0755, true))
			{
				if(!file_exists($redirect_path.'redirect_class.php'))
				{
					// Copy redirect class file.
					copy(dirname(__FILE__).'/DZX25_dzx_redirect_class.php', $redirect_path.'redirect_class.php');
					
					// Generate index module.
					if(!file_exists($redirect_path.'index.php'))
					{
						$redirect_index = new DZX25_Redirect_Generator();
						$redirect_index->generate_file();
						$redirect_index->finish_file();
					}
				}
			}
			else
			{
				// Cannot create the folder, prevent from calling this function again and exit.
				global $import_session;
				if(isset($import_session['DZX25_Redirect_Files_Path']))
				{
					$import_session['DZX25_Redirect_Files_Path'] = '';
				}
				DZX25_Redirect_Generator::$redirect_file_path = '';
			}
		}
		
		$file_path = DZX25_Redirect_Generator::$redirect_file_path . $this->redirect_file_name[$module];
		$this->file_handle = fopen($file_path, "wb");
		
		if(!$this->is_file_good())
		{
			echo "An error prevents creating the redirect file: can't open file handle on '{$file_path}'.<br />";
			return;
		}
		$this->file_open = true;
		$this->module = $module;
		
		if($this->module == 'attachments')
		{
			$this->write_file(str_replace(array('{$MOD_NAME}', '{$TOTAL}'), array("forums, threads, attachments", "{$total}"), $this->redirect_file_header));
		}
		else if($this->module == 'threads')
		{
			$this->write_file(str_replace(array('{$MOD_NAME}', '{$TOTAL}'), array("forums, threads", "{$total}"), $this->redirect_file_header));
		}
		else
		{
			$this->write_file(str_replace(array('{$MOD_NAME}', '{$TOTAL}'), array("{$this->module}", "{$total}"), $this->redirect_file_header));
		}
		$this->write_file("\n\n");
		$this->write_file($this->redirect_file_class_begin[$this->module]);
		$this->write_file("\n");
	}
	
	public function write_record($record)
	{
		$record = rtrim($record);
		if(substr($record, -1) != ',')
		{
			$record .= ",";
		}
		$record .= "\n";
		$this->write_file($record);
		++$this->counter;
	}
	
	public function write_file($data = "")
	{
		if(!$this->is_file_good())
		{
			echo 'An error prevents writing data to the redirect file. No data is written.<br />';
			return;
		}
		fwrite($this->file_handle, $data);
	}
	
	public function finish_file()
	{
		if(!$this->is_file_good())
		{
			echo 'An error prevents writing data to the redirect file. Finishing contents are not written.<br />';
			if($this->file_open)
			{
				$file_close = fclose($this->file_handle);
				$this->file_open = false;
				if(!$file_close)
				{
					echo 'An error prevents closing the redirect file handle.<br />';
				}
			}
			return;
		}
		
		$this->write_file($this->redirect_file_class_end[$this->module]);
		$this->write_file("\n\n");
		$this->write_file($this->redirect_file_body[$this->module]);
		$this->write_file("\n\n");
		if($this->module == 'threads')
		{
			$this->write_file(str_replace(array('{$MOD_NAME}', '{$TOTAL}'), array("forums, {$this->module}", "{$this->counter}"), $this->redirect_file_footer));
		}
		else
		{
			$this->write_file(str_replace(array('{$MOD_NAME}', '{$TOTAL}'), array("{$this->module}", "{$this->counter}"), $this->redirect_file_footer));
		}
		
		if($this->file_open)
		{
			$file_close = fclose($this->file_handle);
			$this->file_open = false;
			if(!$file_close)
			{
				echo 'An error prevents closing the redirect file handle.<br />';
			}
		}
	}
	
	public function __destruct()
	{
		if($this->file_open)
		{
			$file_close = fclose($this->file_handle);
			$this->file_open = false;
			if(!$file_close)
			{
				echo 'An error prevents closing the redirect file handle on class destruction.<br />';
			}
		}
	}
}

function get_utf8_encoded($str, $encoding = "GBK")
{
	if(!function_exists("mb_detect_encoding"))
	{
		return $str;
	}
	
	// mb_* don't support GBK, but its successor GB18030.
	$encoding = strtoupper($encoding);
	if($encoding == "GBK")
	{
		$encoding = "GB18030";
	}
	
	// mb_* supported encodings.
	$encodings = array(
			"UTF-8",
			"GB18030",
			"GB2312",
			"BIG5",
			"ASCII",
	);
	$encoding_detected = mb_detect_encoding($str, $encodings, true);
	
	if($encoding_detected != "UTF-8")
	{
		if(function_exists("iconv"))
		{
			// In iconv, GB2312 is mostly equivalent to CP936.
			if($encoding == "GB2312")
			{
				$encoding == "CP936";
			}
			$str = iconv($encoding_detected, "UTF-8", $str);
			return $str;
		}
	}
	
	return $str;
}


function get_redirect_file_name()
{
	$ret = array(
			'index' => "index.php",
			'users' => "home.php",
			'forums' => "forum.php",
			'threads' => "forum.php",
			'attachments' => "forum.php",
			'attachment_files' => "attachments.php",
	);
	return $ret;
}

function get_redirect_file_header()
{
	$header = <<<'HEADER'
<?php
/**
 * Generated by a Discuz! X2.5 converter of MyBB Merge System.
 * @Module: {$MOD_NAME}
 * @Total of {$MOD_NAME}: {$TOTAL}
 */

// Discuz! X2.5 Redirect starts here.
define("IN_DZX_REDIRECT", true);
define("DZX_REDIRECT_ROOT", dirname(__FILE__).'/');

require_once DZX_REDIRECT_ROOT."redirect_class.php";
HEADER;
	
	return $header;
}

function get_redirect_file_footer()
{
	$footer = <<<'FOOTER'
/**
 * Generated by a Discuz! X2.5 converter of MyBB Merge System.
 * @Module: {$MOD_NAME}
 * @Total records: {$TOTAL}
 */
FOOTER;
	
	return $footer;
}

function get_redirect_file_class_begin()
{
	$class_begin = array();
	
	$class_begin["index"] = <<<'BEGININDEX'
class DZX_Redirect_Index extends DZX_Redirect
{
	public function get_redirect($id, $dz_module_type = '')
	{
		return '';
	}
BEGININDEX;
	
	$class_begin["users"] = <<<'BEGINUSERS'
class DZX_Redirect_Users extends DZX_Redirect
{
	public function get_redirect($id, $dz_module_type = '')
	{
		return self::$redirect_base_url . "{$this->mybb_module}.php?action=profile&uid={$id}";
	}

	public $records = array(
BEGINUSERS;
	
	$class_begin["forums"] = <<<'BEGINFORUMS'
class DZX_Redirect_Forums extends DZX_Redirect
{
	public function get_redirect($id, $dz_module_type = '')
	{
		return self::$redirect_base_url . "{$this->mybb_module}.php?fid={$id}";
	}

	public $records = array(
BEGINFORUMS;
	
	$class_begin["threads"] = <<<'BEGINTHREADS'
class DZX_Redirect_Forums extends DZX_Redirect
{
	public function get_redirect($id, $dz_module_type = '')
	{
		if($dz_module_type == 'forum')
		{
			return self::$redirect_base_url . "{$this->mybb_module}.php?fid={$id}";
		}
		else if($dz_module_type == 'thread')
		{
			return self::$redirect_base_url . "{$this->mybb_module}.php?tid={$id}";
		}
		else
		{
			return '';
		}
	}

	public $records = array(
BEGINTHREADS;
	
	$class_begin["attachments"] = <<<'BEGINATTACHMENTS'
class DZX_Redirect_Forums extends DZX_Redirect
{
	public function get_redirect($id, $dz_module_type = '')
	{
		if($dz_module_type == 'forum')
		{
			return self::$redirect_base_url . "{$this->mybb_module}.php?fid={$id}";
		}
		else if($dz_module_type == 'thread')
		{
			return self::$redirect_base_url . "{$this->mybb_module}.php?tid={$id}";
		}
		else if($dz_module_type == 'attachment')
		{
			return self::$redirect_base_url . "{$this->mybb_module}.php?aid={$id}";
		}
		else
		{
			return '';
		}
	}
			
	public $records = array(
BEGINATTACHMENTS;
	
	$class_begin["attachment_files"] = <<<'BEGINATTACHMENTFILES'
class DZX_Redirect_AttachmentFiles extends DZX_Redirect
{
	public function get_redirect($id, $dz_module_type = '')
	{
		return self::$redirect_base_url . "{$this->mybb_module}.php?aid={$id}";
	}
			
	public $records = array(
BEGINATTACHMENTFILES;
	
	return $class_begin;
}

function get_redirect_file_class_end()
{
	$common_end = <<<'END'
	);
}
END;
	
	$class_end = array();
	
	$class_end["index"] = '}';
	
	$class_end["users"] = $common_end;
	
	$class_end["forums"] = $common_end;
	
	$class_end["threads"] = $common_end;
	
	$class_end["attachments"] = $common_end;
	
	$class_end["attachment_files"] = $common_end;
	
return $class_end;
}

function get_redirect_file_body()
{
	$common_body = <<<'BODY'

BODY;
	
	$body = array();
	
	$body["index"] = 'DZX_Redirect_Index::redirect();';

	$body["users"] = <<<'BODYUSERS'
if($_SERVER['REQUEST_METHOD'] != "GET")
{
	DZX_Redirect::redirect();
	die("Hacking attempt");
}

$dzx_module = 'home';
$mybb_module = 'member';

if(isset($_GET['username']))
{
	$dz_username = $_GET['username'];
}
if(isset($_GET['uid']))
{
	$dz_id = $_GET['uid'];
}

if(isset($dz_id))
{
	$dz_id = intval($dz_id);
	$mybb_redirector = new DZX_Redirect_Users($mybb_module, $dzx_module);
	$id = $mybb_redirector->get_id($dz_id, 'uids');
	$redirect_url = $id === false ? '' : $mybb_redirector->get_redirect($id);
	DZX_Redirect_Users::redirect($redirect_url);
}
else if(isset($dz_username))
{
	if(get_magic_quotes_gpc())
	{
		$dz_username = stripcslashes($dz_username);
	}
	$mybb_redirector = new DZX_Redirect_Users($mybb_module, $dzx_module);
	$dz_username = correct_encoding($dz_username, DZX_Redirect_Users::$encoding);
	$id = $mybb_redirector->get_id($dz_username, 'usernames');
	$redirect_url = $id === false ? '' : $mybb_redirector->get_redirect($id);
	DZX_Redirect_Users::redirect($redirect_url);
}
else
{
	DZX_Redirect::redirect('');
	die("Hacking attempt");
}

function correct_encoding($str, $encoding = "GBK")
{
	if(!function_exists("mb_detect_encoding"))
	{
		return $str;
	}

	// mb_* don't support GBK, but its successor GB18030.
	$encoding = strtoupper($encoding);
	if($encoding == "GBK")
	{
		$encoding = "GB18030";
	}

	// mb_* supported encodings.
	$encodings = array(
		"UTF-8",
		"GB18030",
		"GB2312",
		"BIG5",
		"ASCII",
	);
	$encoding_detected = mb_detect_encoding($str, $encodings, true);

	if($encoding_detected != $encoding)
	{
		if(function_exists("iconv"))
		{
			// In iconv, GB2312 is mostly equivalent to CP936.
			if($encoding == "GB2312")
			{
				$encoding == "CP936";
			}
			if($encoding_detected == "GB2312")
			{
				$encoding_detected == "CP936";
			}
			$str = iconv($encoding_detected, $encoding, $str);
			return $str;
		}
	}
	
	return $str;
}
BODYUSERS;
	
	$body["forums"] = <<<'BODYFORUMS'
if($_SERVER['REQUEST_METHOD'] != "GET")
{
	DZX_Redirect::redirect();
	die("Hacking attempt");
}
			
$dzx_module = 'forum';
$mybb_module = 'forumdisplay';
			
if(isset($_GET['gid']))
{
	$dz_id = $_GET['gid'];
}
						
if(isset($_GET['fid']) && isset($_GET['mod']) && $_GET['mod'] == 'forumdisplay')
{
	$dz_id = $_GET['fid'];
}

if(isset($dz_id))
{
	$dz_id = intval($dz_id);
	$mybb_redirector = new DZX_Redirect_Forums($mybb_module, $dzx_module);
	$id = $mybb_redirector->get_id($dz_id);
	$redirect_url = $id === false ? '' : $mybb_redirector->get_redirect($id);
	DZX_Redirect_Forums::redirect($redirect_url);
}
else
{
	DZX_Redirect::redirect();
	die("Hacking attempt");
}
BODYFORUMS;
	
	$body["threads"] = <<<'BODYTHREADS'
if($_SERVER['REQUEST_METHOD'] != "GET")
{
	DZX_Redirect::redirect();
	die("Hacking attempt");
}
			
$mybb_module = '';
			
if(isset($_GET['gid']))
{
	$dzx_module = 'forum';
	$dz_id = $_GET['gid'];
	$mybb_module = 'forumdisplay';
}
			
if(isset($_GET['fid']) && isset($_GET['mod']) && $_GET['mod'] == 'forumdisplay')
{
	$dzx_module = 'forum';
	$dz_id = $_GET['fid'];
	$mybb_module = 'forumdisplay';
}
			
if(isset($_GET['tid']) && isset($_GET['mod']) && ($_GET['mod'] == 'viewthread' || $_GET['mod'] == 'redirect'))
{
	$dzx_module = 'thread';
	$dz_id = $_GET['tid'];
	$mybb_module = 'showthread';
}
			
if(isset($dz_id))
{
	$dz_id = intval($dz_id);
	$mybb_redirector = new DZX_Redirect_Forums($mybb_module, $dzx_module);
	$id = $mybb_redirector->get_id($dz_id, $dzx_module);
	$redirect_url = $id === false ? '' : $mybb_redirector->get_redirect($id, $dzx_module);
	DZX_Redirect_Forums::redirect($redirect_url);
}
else
{
	DZX_Redirect::redirect();
	die("Hacking attempt");
}
BODYTHREADS;

	
	$body["attachments"] = <<<'BODYATTACHMENTS'
if($_SERVER['REQUEST_METHOD'] != "GET")
{
	DZX_Redirect::redirect();
	die("Hacking attempt");
}
			
$mybb_module = '';
			
if(isset($_GET['gid']))
{
	$dzx_module = 'forum';
	$dz_id = $_GET['gid'];
	$mybb_module = 'forumdisplay';
}
			
if(isset($_GET['fid']) && isset($_GET['mod']) && $_GET['mod'] == 'forumdisplay')
{
	$dzx_module = 'forum';
	$dz_id = $_GET['fid'];
	$mybb_module = 'forumdisplay';
}
			
if(isset($_GET['tid']) && isset($_GET['mod']) && ($_GET['mod'] == 'viewthread' || $_GET['mod'] == 'redirect'))
{
	$dzx_module = 'thread';
	$dz_id = $_GET['tid'];
	$mybb_module = 'showthread';
}

if(isset($_GET['aid']) && isset($_GET['mod']) && $_GET['mod'] == 'attachment')
{
	$dzx_module = 'attachment';
	@list($_GET['aid'], $_GET['k'], $_GET['t'], $_GET['uid'], $_GET['tableid']) = explode('|', base64_decode($_GET['aid']));
	$dz_id = $_GET['aid'];
	$mybb_module = 'attachment';
}
			
if(isset($dz_id))
{
	$dz_id = intval($dz_id);
	$mybb_redirector = new DZX_Redirect_Forums($mybb_module, $dzx_module);
	$id = $mybb_redirector->get_id($dz_id, $dzx_module);
	$redirect_url = $id === false ? '' : $mybb_redirector->get_redirect($id, $dzx_module);
	DZX_Redirect_Forums::redirect($redirect_url);
}
else
{
	DZX_Redirect::redirect();
	die("Hacking attempt");
}
BODYATTACHMENTS;
	
	$body["attachment_files"] = <<<'BODYATTACHMENTFILESS'
if($_SERVER['REQUEST_METHOD'] != "GET")
{
	DZX_Redirect::redirect();
	die("Hacking attempt");
}
			
$dzx_module = '';
$mybb_module = 'attachment';
			
if(isset($_GET['attachment_filename']))
{
	$attachment_filename = $_GET['attachment_filename'];
}
			
if(isset($attachment_filename))
{
	if(get_magic_quotes_gpc())
	{
		$attachment_filename = stripcslashes($attachment_filename);
	}
	$mybb_redirector = new DZX_Redirect_AttachmentFiles($mybb_module, $dzx_module);
	$attachment_filename = correct_encoding($attachment_filename, DZX_Redirect_AttachmentFiles::$encoding);
	$id = $mybb_redirector->get_id($attachment_filename);
	$redirect_url = $id === false ? '' : $mybb_redirector->get_redirect($id);
	DZX_Redirect_AttachmentFiles::redirect($redirect_url);
}
else
{
	DZX_Redirect::redirect('');
	die("Hacking attempt");
}
		
function correct_encoding($str, $encoding = "GBK")
{
	if(!function_exists("mb_detect_encoding"))
	{
		return $str;
	}
		
	// mb_* don't support GBK, but its successor GB18030.
	$encoding = strtoupper($encoding);
	if($encoding == "GBK")
	{
		$encoding = "GB18030";
	}
		
	// mb_* supported encodings.
	$encodings = array(
		"UTF-8",
		"GB18030",
		"GB2312",
		"BIG5",
		"ASCII",
	);
	$encoding_detected = mb_detect_encoding($str, $encodings, true);
		
	if($encoding_detected != $encoding)
	{
		if(function_exists("iconv"))
		{
			// In iconv, GB2312 is mostly equivalent to CP936.
			if($encoding == "GB2312")
			{
				$encoding == "CP936";
			}
			if($encoding_detected == "GB2312")
			{
				$encoding_detected == "CP936";
			}
			$str = iconv($encoding_detected, $encoding, $str);
			return $str;
		}
	}
		
	return $str;
}
BODYATTACHMENTFILESS;
	
	return $body;
}
