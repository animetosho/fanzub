<?php // coding: latin1
/**
 * Cache (Memcache) Class
 */

// Check for Memcache extension
if (!extension_loaded('memcache'))
	throw new ErrorException('Memcache extension not found. Check PHP configuration');

class Cache extends Memcache
{
	const DEFAULT_EXPIRE   = 3600;

	protected $status = false;
	protected $name = '';

	public function __construct($server = 'localhost',$port = 11211)
	{
		$this->name = (isset($GLOBALS['config']['cache']['name']) ? $GLOBALS['config']['cache']['name'] : '');
		$this->status = $this->connect($server,$port);
	}
	
	/**
	 * Magic method for getting properties.
	 */ 
	public function __get($name)
	{
		switch($name)
		{
      case 'status':
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
		if ($this->status)
			return parent::get($this->Key($module,$key));
		else
			return false;
	}
	
	public function Add($module,$key,$value,$expire = self::DEFAULT_EXPIRE)
	{
		if ($this->status)
			parent::add($this->Key($module,$key),$value,0,$expire);
	}

	public function Set($module,$key,$value,$expire = self::DEFAULT_EXPIRE)
	{
		if ($this->status)
			parent::set($this->Key($module,$key),$value,0,$expire);
	}
	
	public function Delete($module,$key)
	{
		if ($this->status)
			parent::delete($this->Key($module,$key));
	}

	public function Flush()
	{
		if ($this->status)
			parent::flush();
	}
	
	public function Stats()
	{
		if ($this->status)
			return parent::getStats();
		else
			return array();
	}
}

?>