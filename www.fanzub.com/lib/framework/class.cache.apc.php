<?php // coding: latin1
/**
 * Cache (APCu) Class
 */

// Check for Memcache extension
if (!extension_loaded('apc'))
	throw new ErrorException('APC(u) extension not found. Check PHP configuration');

class Cache
{
	const DEFAULT_EXPIRE   = 3600;

	protected $name = '';

	public function __construct()
	{
		$this->name = (isset($GLOBALS['config']['cache']['name']) ? $GLOBALS['config']['cache']['name'] : '');
	}
	
	/**
	 * Magic method for getting properties.
	 */ 
	public function __get($name)
	{
		switch($name)
		{
      case 'name':
        return $this->{$name};
      
			default:
				throw new ErrorException('Undefined property '.get_class($this).'::$'.$name,0,E_WARNING);
		}
	}
	
	protected function Key($module,$key)
	{
		return $this->name.'::'.$module.'::'.$key;
	}
	
	public function Get($module,$key)
	{
		return apc_fetch($this->Key($module,$key));
	}
	
	public function Set($module,$key,$value,$expire = self::DEFAULT_EXPIRE)
	{
		apc_store($this->Key($module,$key),$value,$expire);
	}
	
	// following functions are never actually used
	public function Add($module,$key,$value,$expire = self::DEFAULT_EXPIRE)
	{
		apc_add($this->Key($module,$key),$value,$expire);
	}

	public function Delete($module,$key)
	{
		apc_delete($this->Key($module,$key));
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