<?php
use PhpParser\Node;


trait OOEmulator_spl_autoload 
{
	protected $autoloaders=[];
	public function spl_autoload_register($callback=null, $throw=true, $prepend=false)
	{
		if ($callback===null)
			$callback="spl_autoload"; //default autoloader of php
		if ($prepend)
			array_unshift($this->autoloaders, $prepend);
		else
			$this->autoloaders[]=$callback;
		return true;
	}
	public function spl_autoload_unregister($callback)
	{
		if ( ($key=array_search($callback, $this->autoloaders))!==false)
		{
			unset($this->autoloaders[$key]);
			return true;
		}
		return false;

	}
	public function spl_autoload_functions()
	{
		return $this->autoloaders;
	}
	public function spl_autoload_call($class)
	{
		foreach ($this->autoloaders as $autoloader)
			if ($this->class_exists($class)) break;
			else $this->call_function($autoloader,[$class]);
	}
	protected $autoload_extensions=".inc,.php";
	public function spl_autoload_extensions($extensions=null)
	{
		if ($extensions===null) return $this->autoload_extensions;
		spl_autoload_extensions($extensions);
		$this->autoload_extensions=$extensions;
	}
	function autoload($class)
	{
		return $this->spl_autoload_call($class);
	}
}