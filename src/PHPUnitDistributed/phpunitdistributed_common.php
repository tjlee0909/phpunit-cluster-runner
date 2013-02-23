<?php
namespace PHPUnitDistributed;

define('PHPUNITDISTRIBUTED_DIR', dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR);
define('PHPUNITDISTRIBUTED_SRC', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'PHPUnitDistributed' . DIRECTORY_SEPARATOR);
require_once(PHPUNITDISTRIBUTED_SRC . 'Autoloader.php');

Autoloader::register_autoload_path(PHPUNITDISTRIBUTED_SRC);

if (!ini_get('date.timezone')) {
	// Prevent unwanted PHP warnings when dealing with dates in any capacity
	date_default_timezone_set('UTC');
}
