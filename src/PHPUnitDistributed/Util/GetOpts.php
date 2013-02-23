<?php
namespace PHPUnitDistributed\Util;

require_once __DIR__ . '/getopt.php';

/**
 * Little trick class to make autoloader find the getopt file, yay global functions!
 */
class GetOpts
{
	public static function parse($options, $fromarr = null)
	{
		return getopts($options, $fromarr);
	}
}

