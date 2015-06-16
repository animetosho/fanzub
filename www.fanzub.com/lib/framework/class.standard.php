<?php // coding: latin1
/**
 * Standard Classes and Functions
 *
 * Copyright 2008-2011 Fanzub.com. All rights reserved.
 * Do not distribute this file whole or in part without permission.
 *
 * $Id: class.standard.php 12 2012-04-26 13:58:59Z ghdpro $
 * @package Visei_Framework
 */

// Defines
define('YES',1);
define('NO',0);
define('ACTIVE',1);
define('INACTIVE',0);
define('DATE_NEVER',2147483647);

$page_start = GetMicroTime();

// PHP version check
if (!function_exists('version_compare'))
	throw new ErrorException('Function <code>version_compare</code> does not exist');
if (version_compare(phpversion(),'5.3.0') == -1)
 	throw new ErrorException('This site requires PHP 5.3.x or newer');

// No magic quotes
if (get_magic_quotes_gpc())
	throw new ErrorException('This site requires the <code>magic_quotes</code> PHP setting to be turned <u>off</u>');
	
// No register globals
if (ini_get('register_globals'))
	throw new ErrorException('This site requires the <code>register_globals</code> PHP setting to be turned <u>off</u>');

// Check for injection attack
$invalid_request_var = array('GLOBALS','_SERVER','HTTP_SERVER_VARS','_GET','HTTP_GET_VARS','_POST','HTTP_POST_VARS','_COOKIE','HTTP_COOKIE_VARS','_FILES','HTTP_POST_FILES','_ENV','HTTP_ENV_VARS','_REQUEST','_SESSION','HTTP_SESSION_VARS');
foreach ($_REQUEST as $key=>$value)
	if (in_array($key,$invalid_request_var))
	{
		header('HTTP/1.1 500 Internal Server Error');
		die('<h1>500 Internal Server Error</h1>Attempt to overwrite super-globals rejected.');
	}

// Unicode
if (!extension_loaded('mbstring'))
	throw new ErrorException('Multibyte String extension not found. Check PHP configuration');
mb_internal_encoding('UTF-8');

// Auto-Install UncaughtExceptionHandler
set_exception_handler('DumpException');

// Auto-Install ExceptionErrorHandler
set_error_handler('ExceptionErrorHandler');

class CustomException extends Exception
{
	protected $custominfo = null;
	protected $customlabel = '';
	
	function getMessageText()
	{
		return htmlspecialchars_decode(strip_tags(str_replace(array('<i>','</i>','<b>','</b>','<code>','</code>'),'"',$this->getMessage())));
	}
	
	function getCustomInfo()
	{
		return $this->custominfo;
	}
	
	function getCustomLabel()
	{
		return $this->customlabel;
	}
}

// Database Exceptions
class DatabaseException extends CustomException
{
	public function __construct($message = null,$code = 0,$query = '')
	{
		parent::__construct($message,$code);
		if (!empty($query))
		{
			$this->custominfo = $query;
			$this->customlabel = 'Query';
		}
	}
}
class SQLException extends DatabaseException {}

if (extension_loaded('sqlite3'))
{
	class Journal
	{
		const FATAL = 'fatal';
		const WARNING = 'warning';
		const NOTICE = 'notice';
		const REPEAT_TIMEOUT = 3600;
		const TIMEOUT = 10;
		const SQLITE_BUSY = 5;

		public static function Log($level,$class,$message,$details = '',$object = '')
		{
			// Logging is disabled if database is set to false
			if (isset($GLOBALS['config']['path']['journal']) && ($GLOBALS['config']['path']['journal'] === false))
				return;
			// Otherwise, database must exist
			if (!isset($GLOBALS['config']['path']['journal']) || empty($GLOBALS['config']['path']['journal']))
				throw new Exception('No journal database specified');
			if (!file_exists($GLOBALS['config']['path']['journal']))
				throw new Exception('Journal database not found');
			// Init database
			$conn = new SQLite3($GLOBALS['config']['path']['journal']);
			// Init values
			$values = array();
			$values['level'] = "'".$conn->escapeString($level)."'";
			$values['class'] = "'".$conn->escapeString($class)."'";
			if (isset($_SERVER['REMOTE_ADDR']))
				$values['userip'] = "'".$conn->escapeString($_SERVER['REMOTE_ADDR'])."'";
			else
				$values['userip'] = "''";
			$values['message'] = "'".$conn->escapeString($message)."'";
			$values['details'] = "'".$conn->escapeString(substr((string)$details,0,1024))."'";
			if (isset($_SERVER['REQUEST_URI']))
				$values['request'] = "'".$conn->escapeString($_SERVER['REQUEST_URI'])."'";
			else
				$values['request'] = "''";
			if (isset($_SERVER['SCRIPT_NAME']))
				$values['script'] = "'".$conn->escapeString($_SERVER['SCRIPT_NAME'])."'";
			elseif (isset($_SERVER['PHP_SELF']))
				$values['script'] = "'".$conn->escapeString($_SERVER['PHP_SELF'])."'";
			else
				$values['script'] = "''";
			if (is_array($object))
				$values['object'] = "'".$conn->escapeString(implode(',',$object))."'";
			else
				$values['object'] = "'".$conn->escapeString($object)."'";
			// Check repeats
			$query = 'SELECT id FROM journal WHERE ';
			$where = '';
			foreach($values as $field=>$value)
			{
				if (!empty($where))
					$where .= ' AND ';
				$where .= '('.$field.' = '.$value.')';
			}																				
			$where .= ' AND (date_added > '.(time()-self::REPEAT_TIMEOUT).')';
			$rs = $conn->query($query.$where);
			$row = $rs->fetchArray();
			if ($row !== false)
			{
				// Increase repeat counter
				$query = 'UPDATE journal ';
				$query .= 'SET repeats = repeats + 1,date_repeat = '.time().' ';
				$query .= 'WHERE id = '.$row['id'];
			}
			else
			{
				// Insert journal entry
				$values['id'] = 'NULL';
				$values['date_added'] = time();
				$fields = implode('`,`',array_keys($values));
				$data = implode(',',array_values($values));
				$query = 'INSERT INTO journal (`'.$fields.'`) VALUES('.$data.')';
			}
			// SQLite does not always honor its own busy timeout handler
			// So, if we get busy error we simply try again until we succeed or timeout ourselves
			$start = time();
			$retry = true;
			$tries = 1;
			while($retry)
			{
				try {
					$conn->exec($query);
					$retry = false;
				} catch (Exception $e) {
					// If busy then ignore it until timeout has lapsed. If not, throw exception again.
					if ($conn->lastErrorCode() != self::SQLITE_BUSY)
						throw $e;
					if ((time() - $start) > self::TIMEOUT)
						throw new DatabaseException('Unable to write to database; still locked after waiting '.self::TIMEOUT.' seconds',$conn->lastErrorCode(),$query);
					usleep(10000 * $tries); // 10ms times number of tries
					$tries++;
				}
			}
		}
	}
}

abstract class Cron
{
  protected static $config = null;
  protected static $conn = null;
  protected static $cache = null;
  protected $time = array();

  public function __construct()
  {
    if (is_null(static::$config) && isset($GLOBALS['config']))
      static::$config = $GLOBALS['config'];
    if (is_null(static::$conn) && isset($GLOBALS['conn']))
      static::$conn = $GLOBALS['conn'];
    if (is_null(static::$cache) && isset($GLOBALS['cache']))
      static::$cache = $GLOBALS['cache'];
    $this->time['start'] = GetMicroTime();
    $this->Limits();
  }
  
  public function __destruct()
  {
    echo $this->Stats();
  }
  
  protected function Limits($memory = '256M',$time = 600)
  {
    ini_set('memory_limit',$memory);
    set_time_limit($time);
  }
  
  protected function Title($title)
  {
    return '<h2>Cron '.$title.'</h2><p><i>'.date('r').'</i></p>'."\n";
  }
  
  protected function Stats()
  {
    $this->time['end'] = GetMicroTime();
    $duration = $this->time['end'] - $this->time['start'];
    return '<p><i>Statistics</i><br />'."\n"
          .'Total time: <b>'.number_format($duration,2).'</b> seconds ('
          .'php: '.number_format(abs($duration-static::$conn->duration),3).'s - '
          .'memory: '.number_format(memory_get_peak_usage()/1048576,1).' MiB - '
          .'sql: '.number_format(static::$conn->duration,3).'s / '.static::$conn->count." queries)</p>\n";
  }
  
  public abstract function Run();
}

function ExceptionErrorHandler($severity,$message,$filename,$line)
{
	if (error_reporting() == 0)
		return;
	if (error_reporting() & $severity)
	{
		if ($severity == E_NOTICE)
		{
			if (defined('DEBUG') && class_exists('Journal'))
				Journal::Log(Journal::NOTICE,'Notice',$message,$filename.':'.$line);
		}
		else
			throw new ErrorException($message,0,$severity,$filename,$line);
	}
}

function DumpException($exception)
{
	// Basic information
	$header = '<br /><b>'.get_class($exception).':</b> '."\n";
	$message = $exception->getMessage().' ';
	if ($exception->getCode() != 0)
		$message .= '(<code>'.$exception->getCode().'</code>) '."\n";
	$footer = '';
	$file = $exception->getFile();
	if (!empty($file))
	{
		$footer .= 'in <b>'.$exception->getFile().'</b> '."\n";
		$footer .= 'on line <b>'.$exception->getLine().'</b>';
	}
	$footer .= '<br />'."\n";
	if (defined('DEBUG'))
		echo $header.$message.$footer;
	else
		echo '<br />An unhandled <b>'.get_class($exception).'</b> was thrown. Please contact an administrator if this problem persists.<br />'."\n";
	// Debug information
	$details = '<p>';
	if (method_exists($exception,'getCustomLabel'))
	{
		$customlabel = $exception->getCustomLabel();
		if (!empty($customlabel))
		{
			$details .= '<i>'.$customlabel.'</i><br />'."\n\n";
			$details .= '<pre>';
			$details .= $exception->getCustomInfo();
			$details .= '</pre>'."\n";
		}
	}
	$details .= '<i>Trace</i><br />'."\n\n";
	$details .= '<pre>';
	$details .= $exception->getTraceAsString();
	$details .= '</pre>'."\n";
	$details .= '</p>';
	if (defined('DEBUG'))
		echo $details;
	if(!class_exists('Journal')) return;
	// Log exception
	$object = '';
	if (!empty($file))
		$object = $exception->getFile().':'.$exception->getLine();
	try {
		Journal::Log(Journal::FATAL,get_class($exception),trim(strip_tags($message)),trim(strip_tags($details)),$object);
	} catch (Exception $e) {
		die('<br /><b>Error:</b> '.$e->getMessage()).'<br />'."\n";
	}
}

function GetMicroTime()
{ 
	list($usec,$sec) = explode(' ',microtime());
	return ((float)$usec + (float)$sec);
}

function SafeHTML($string)
{
	return htmlspecialchars(FixUnicode($string),ENT_COMPAT,'UTF-8',false);
}

function FixUnicode($string)
{
	if ((mb_detect_encoding($string) == 'UTF-8') && mb_check_encoding($string,'UTF-8'))
		return $string;
	else
		return utf8_encode($string);
}

function FixURL($url)
{	
	$url = rawurldecode($url); // Prevent double escaping
	$url = str_replace(array('&amp;','&#38;'),'&',$url); // Decode "&" characters
	$parts = @parse_url($url);
	$result = ''; // Re-assemble URL
	if (isset($parts['scheme']) && !empty($parts['scheme']))
		$result .= rawurlencode($parts['scheme']).'://';
	if (isset($parts['user']) && !empty($parts['user']))
		$result .= rawurlencode($parts['user']);
	if (isset($parts['pass']) && !empty($parts['pass']))
		$result .= ':'.rawurlencode($parts['pass']);
	if (isset($parts['user']) && !empty($parts['user']))
		$result .= '@';
	if (isset($parts['host']) && !empty($parts['host']))
		$result .= rawurlencode($parts['host']);
	if (isset($parts['port']) && !empty($parts['port']))
		$result .= ':'.intval($parts['port']);
	if (isset($parts['path']) && !empty($parts['path']))
		$result .= str_replace(array('%2F','%7E'),array('/','~'),rawurlencode($parts['path'])); // Some webservers prefer "~" over %7E
	if (isset($parts['query']) && !empty($parts['query']))
		$result .= '?'.str_replace(array('%26','%3D'),array('&','='),rawurlencode($parts['query'])); // Be careful with url encoding query string
	return $result;
}

function is_truish($bool)
{
	switch((string)strtolower(trim($bool)))
	{
		case 't':
		case 'true':
		case 'y':
		case 'yes':
		case 'on':
		case '1':
			return true;
	}
	return false;
}

function FormatSize($size,$decimals = 1)
{
	if ($size < 1024)
		return $size.' B';
	elseif ($size < 1048576)
		return ceil($size / 1024).' KiB';
	elseif ($size < 1073741824)
		return number_format(floatval($size) / 1048576,$decimals).' MiB';
	else
		return number_format(floatval($size) / 1073741824,$decimals).' GiB';
}

// only ever used from cron.php
function LoadAverage()
{
	if (file_exists('/proc/loadavg'))
	{
		$result = array();
		$data = file_get_contents('/proc/loadavg');
		$parts = explode(' ',$data);
		if (isset($parts[0]))
			$result[0] = floatval($parts[0]);
		if (isset($parts[1]))
			$result[1] = floatval($parts[1]);
		if (isset($parts[2]))
			$result[2] = floatval($parts[2]);
		return $result;
	}
	else
		return false;
}

function Paginator($url,$value,$max,$suffix = '')
{
	$result = '<div class="paginator">';
	// <<
	if ($value > 1)
		$result .= '<a href="'.$url.($value-1).$suffix.'" class="arrow" title="Previous">&lt;&lt;</a>';
	else
		$result .= '<span class="inactive" title="Previous">&lt;&lt;</span>';
	// Previous
	if ($value > 1)
	{
		if ($value > 5)
		{
			$result .= ' <a href="'.$url.'1'.$suffix.'">1</a> ... ';
			for ($i = ($value-2); $i < $value; $i++)
				$result .= ' <a href="'.$url.$i.$suffix.'">'.$i.'</a> ';
		}
		else
		{
			for ($i = 1; $i < $value; $i++)
				$result .= ' <a href="'.$url.$i.$suffix.'">'.$i.'</a> ';
		}
	}
	// Current
	if ($max > 1)
		$result .= ' <span class="current">'.$value.'</span> ';
	// Next
	if ($value < $max)
	{
		if ($value < ($max-4))
		{
			for ($i = ($value+1); $i < ($value+3); $i++)
				$result .= ' <a href="'.$url.$i.$suffix.'">'.$i.'</a> ';
			$result .= ' ... <a href="'.$url.$max.$suffix.'">'.$max.'</a> ';
		}
		else
		{
			for ($i = ($value+1); $i <= $max; $i++)
				$result .= ' <a href="'.$url.$i.$suffix.'">'.$i.'</a> ';
		}
	}
	// >>
	if ($value < $max)
		$result .= '<a href="'.$url.($value+1).$suffix.'" class="arrow" title="Next">&gt;&gt;</a>';
	else
		$result .= '<span class="inactive" title="Next">&gt;&gt;</span>';
	$result .= '</div>'."\n";
	return $result;
}
?>