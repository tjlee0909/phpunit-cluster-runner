<?php
namespace PHPUnitDistributed\JUnit;

/**
 * Represents a testsuite node in a JUnit result.
 */
abstract class TestsuiteAbstract
{
	/**
	 * Return the <testsuite>...</testsuite> XML element.
	 *
	 * @abstract
	 * @return \SimpleXMLElement
	 */
	abstract public function xml();

	/**
	 * Absolute path to the test ran in the testsuite.
	 *
	 * @abstract
	 * @return string|null
	 */
	abstract public function file();

	/**
	 * Time (in seconds) with decimal-precision of duration of test run.
	 *
	 * @abstract
	 * @return float|null
	 */
	abstract public function time();

	/**
	 * The number of tests that were run.
	 *
	 * @abstract
	 * @return int|null
	 */
	abstract public function tests();

	/**
	 * The number of errors encountered.
	 *
	 * @abstract
	 * @return int|null
	 */
	abstract public function errors();

	/**
	 * The number of fatals encountered.
	 *
	 * @abstract
	 * @return int|null
	 */
	abstract public function failures();
}
