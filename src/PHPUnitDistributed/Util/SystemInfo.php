<?php
namespace PHPUnitDistributed\Util;
/**
 * Provides information about the System.
 */
class SystemInfo
{
	 /**
	  * Returns true when run on Windows and false on all Unix (or non-windows) flavors
	  */
	public function is_windows_os()
	{
		return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
	}

	public function is_mac_os()
	{
		return strtoupper(PHP_OS) === 'DARWIN';
	}

	public function use_color()
	{
		if (!array_key_exists('BOX_COLOR', $_ENV)) return true;

		return (bool) $_ENV['BOX_COLOR'];
	}

	public function host_name()
	{
		return gethostname();
	}
}

