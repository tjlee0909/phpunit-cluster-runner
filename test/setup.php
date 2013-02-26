<?php

error_reporting(E_ALL);

// $root/test/setup.php
$root = dirname(__DIR__) . '/';
require_once $root . 'src/PHPUnitDistributed/phpunitdistributed_common.php';

// So tests can use static methods in other tests
\PHPUnitDistributed\Autoloader::register_autoload_path($root . 'test');

date_default_timezone_set('America/Los_Angeles');

require_once $root . 'test/PHPUnitDistributed/BaseTestCase.php';