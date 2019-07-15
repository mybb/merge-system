<?php
/**
 * Provided by a Discuz! X2.5 converter of MyBB Merge System.
 * @Module: index
 * @Total: 0
 */

/**
 * Overwrite this define with your MyBB's FULL URL.
 * @var string
 */
define("MYBB_URL", "http://my.mybb.com");
/**
 * We output the $this->records data in UTF-8 encoding. All files generated are in UTF-8 format.
 * @var string
 */
define("DZX_REDIRECT_ENCODING", "UTF-8");

// Disallow direct access to this file for security reasons
if(!defined("IN_DZX_REDIRECT"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_DZX_REDIRECT is defined.");
}

abstract class DZX_Redirect
{
	/**
	 * The base URL we redirect to, it's the MyBB URL.
	 * @var string
	 */
	public static $redirect_base_url = '';
	
	/**
	 * The encoding/charset of your old Discuz! X2.5 database.
	 * @var string
	 */
	public static $encoding = 'gbk';
	
	/**
	 * Records array for each module. The array key is your old Discuz! X2.5 index, the array value is the record's new index in the MyBB database.
	 * @var array
	 */
	public $records;
	
	/**
	 * Discuz! X2.5 module.
	 * @var string
	 */
	public $dzx_module = '';
	
	/**
	 * MyBB module.
	 * @var string
	 */
	public $mybb_module = '';
	
	public function __construct($mybb_module, $dz_module = '')
	{
		$mybb_url = self::$redirect_base_url;
		if(substr($mybb_url, -1) != '/')
		{
			$mybb_url .= '/';
		}
		self::$redirect_base_url = $mybb_url;
		
		$this->mybb_module = $mybb_module;
		$this->dzx_module = $dz_module;
	}
	
	public function load_data()
	{
		global $DZ_REDIRECT_DATA;
		$this->records = $DZ_REDIRECT_DATA;
	}
	
	/**
	 * Get the new index in the MyBB database, given an old Discuz! X2.5 index.
	 * @param int $old_id The index in your old Discuz! X2.5.
	 * @return int|bool The index in the MyBB database, or false if the record is not found.
	 */
	public function get_id($old_id, $array_key = '')
	{
		// Don't make a new var.
		if(!empty($array_key) && isset($this->records[$array_key]))
		{
			if(!empty($this->records[$array_key]) && (isset($this->records[$array_key][$old_id]) || array_key_exists($old_id, $this->records[$array_key])))
			{
				return $this->records[$array_key][$old_id];
			}
			
			return false;
		}
		else if(!empty($this->records) && (isset($this->records[$old_id]) || array_key_exists($old_id, $this->records)))
		{
			return $this->records[$old_id];
		}
		
		return false;
	}
	
	/**
	 * Get the redirect URL, given a MyBB index and ogrinal module's type.
	 * @param int $id The index of record in MyBB.
	 * @param string $dz_module_type The module's type in your old Discuz!.
	 * @return string The full redirect URL.
	 */
	public abstract function get_redirect($id, $dz_module_type = '');
	
	public static function redirect($redirect_url = '', $extra_on_false = '')
	{
		if(empty($redirect_url))
		{
			$redirect_url = self::$redirect_base_url;
		}
		
		if(!filter_var($redirect_url, FILTER_VALIDATE_URL))
		{
			header("Content-type: text/html; charset=" . self::$encoding);
			header("HTTP/1.1 404 Not Found");
			$message = "<html><lang lang=\"cn\"><head><title>The requested resource is not found</title>";
			// TODO: uncomment next line to enable auto refresh.
			//$message = "<meta http-equiv=\"refresh\" content=\"5; url=". self::$redirect_base_url ."\">";
			$message .= "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=" . self::$encoding . "\" />";
			$message .= "</head><body><h1>Discuz! X2.5 Redirect Error:</h1><div><p><strong>The redirect URL is invalid:</strong> {$redirect_url}<br />";
			if(!empty($extra_on_false))
			{
				$message .= "{$extra_on_false}";
			}
			$message .= "</p></div>";
			$message .= "<h2>Redirecting to <a href=\"". self::$redirect_base_url ."\">". self::$redirect_base_url ."</a> ...</h2>";
			$message .= "</body></html>";
			die($message);
		}
		
		// TODO: comment next line to enable redirect.
		die($redirect_url);
		header("Location: {$redirect_url}", true, 301);
		exit();
	}
}

DZX_Redirect::$redirect_base_url = MYBB_URL;
DZX_Redirect::$encoding = DZX_REDIRECT_ENCODING;

