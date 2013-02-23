<?php
namespace PHPUnitDistributed\PHPUnit;

use PHPUnitDistributed\JUnit\ResultAbstract;

/**
 * Represents the phpunit command line tool. All PHPUnit invocation implementations should implement
 * this interface.
 */
interface IRun
{
	/**
	 * Execute PHPUnit!
	 *
	 * @abstract
	 * @return bool - returns true if PHPUnit ran successfully
	 */
	public function run();

	/**
	 * This field was made public so that the invoker can keep a reference to the PHPUnit object
	 * and access its configuration to view where the results are being written to.
	 *
	 * @abstract
	 * @return Configuration - the configuration for this PHPUnit run
	 */
	public function config();
}
