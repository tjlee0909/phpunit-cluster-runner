<?php
namespace PHPUnitDistributed\Util;
/**
 * Utility class for wrapping global functions. Useful when
 * testing classes that have references to global functions like
 * setcookie or redirect_and_exit.
 *
 */
class GlobalFunctions
{
	private static $stubbed_functions = array();

	public static function stub_function($fn_name, $fn_closure)
	{
		self::$stubbed_functions[$fn_name] = $fn_closure;
	}

	public static function reset_stubs()
	{
		self::$stubbed_functions = array();
	}

	/**
	 * @return a closure that calls the global function specified.
	 */
	public static function __callStatic($name, $args)
	{
		if (array_key_exists($name, self::$stubbed_functions))
		{
			$fn = self::$stubbed_functions[$name];
			return call_user_func_array($fn, $args);
		}
		else
		{
			return call_user_func_array($name, $args);
		}
	}

}
