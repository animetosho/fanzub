<?php // coding: latin1
/**
 * Cache (Null) Class
 * 
 * Use if you don't want any cache
 */

class Cache
{
	public function Get($module,$key)
	{
		return null;
	}
	
	public function Set($module,$key,$value,$expire = self::DEFAULT_EXPIRE)
	{
	}
	
	// following functions are never actually used
	public function Add($module,$key,$value,$expire = self::DEFAULT_EXPIRE)
	{
	}
	public function Delete($module,$key)
	{
	}
	public function Flush()
	{
	}
	public function Stats()
	{
		return array();
	}
}

?>