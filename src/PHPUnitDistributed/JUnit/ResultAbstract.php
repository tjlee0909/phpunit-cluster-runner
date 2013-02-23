<?php
namespace PHPUnitDistributed\JUnit;

/**
 * A JUnit result.
 */
abstract class ResultAbstract
{
	/**
	 * Return the XML element of the JUnit result.
	 * The root element should be a <testsuites>...</testsuites> node.
	 *
	 * @abstract
	 * @return \SimpleXMLElement
	 */
	abstract public function xml();

	/**
	 * Get all testsuites that compose this JUnit result.
	 *
	 * @abstract
	 * @return TestsuiteAbstract[]
	 */
	abstract public function testsuites();
}
