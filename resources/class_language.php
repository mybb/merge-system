<?php
/**
* MyBB 1.8 Merge System
* Copyright 2014 MyBB Group, All Rights Reserved
*
* Website: http://www.mybb.com
* License: http://www.mybb.com/download/merge-system/license/
*/
class debugMyLanguage extends MyLanguage {
	function count($string, $num, $sprintf=true)
	{
		if($num != 1)
		{
			$pl = $string."_plural";
			if(isset($this->$pl))
			{
				$string .= "_plural";
			}
		}
		if($sprintf)
		{
			return $this->sprintf($this->$string, $num);
		}
		return $this->$string;
	}
}