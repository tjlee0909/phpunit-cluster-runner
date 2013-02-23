<?php
namespace PHPUnitDistributed\JUnit;

use PHPUnitDistributed\Util\Witness;
use PHPUnitDistributed\Util\File;
use PHPUnitDistributed\Util\GlobalFunctions;

/**
 * Represents a single test result of a PHPUnit run. Specifically, this class wraps a JUnit-formatted XML file.
 */
class XmlResult extends ResultAbstract
{
	/** @var Witness */
	private $witness;
	/** @var \SimpleXMLElement */
	private $junit_xml;
	/** @var XmlTestsuite[] */
	private $testsuites;

	/**
	 * @param File $file - File object that represents the JUnit XML file
	 * @param Witness $witness
	 */
	public function __construct($file, $witness)
	{
		$this->witness = $witness;

		if (!$file->exists())
		{
			$this->witness->log_error($file->path() . ' does not exist.');
			return;
		}

		$this->junit_xml = GlobalFunctions::simplexml_load_file($file->path());

		if (!$this->junit_xml)
		{
			$this->witness->log_error('Failed to parse XML file ' . $file->path());
			return;
		}
	}

	public function xml()
	{
		return $this->junit_xml;
	}

	/**
	 * Returns all of the testsuites for this JUnit result
	 *
	 * @return XmlTestsuite[]
	 */
	public function testsuites()
	{
		if (!$this->testsuites)
		{
			$this->testsuites = array();
			$testsuite_nodes = $this->junit_xml->xpath('/testsuites/testsuite');

			if ($testsuite_nodes)
			{
				foreach ($testsuite_nodes as $testsuite_node)
				{
					// This is sort of a hack, but in our PHPUnit JUnit results, testsuite nodes are
					// nested within one another, with the outer testsuite having nothing of value
					// in it, so sometimes it is necessary to go one level deeper.
					$testsuite = $this->find_nested_testsuite_with_file_attribute($testsuite_node);

					if ($testsuite)
					{
						$this->testsuites[] = $testsuite;
					}
				}
			}
		}

		return $this->testsuites;
	}

	/**
	 * Returns the PHPUnit_JUnit_XmlTestsuite of <testsuite> within $testsuite_node that has the
	 * 'file' attribute set. Returns null if not found.
	 *
	 * @param \SimpleXMLElement $testsuite_node - the top-level testsuite node to inspect
	 * @return XmlTestsuite
	 */
	private function find_nested_testsuite_with_file_attribute($testsuite_node)
	{
		// Base case: passed in node has a file attribute
		$testsuite = new XmlTestsuite($testsuite_node);

		if ($testsuite->file()) return $testsuite;

		$testsuites_in_testsuite = $testsuite_node->xpath('testsuite');

		if (count($testsuites_in_testsuite) == 0) return null;

		// Iterate through child testsuite nodes and do a recursive check
		foreach ($testsuites_in_testsuite as $sub_testsuite_node)
		{
			$sub_testsuite = $this->find_nested_testsuite_with_file_attribute($sub_testsuite_node);

			if ($sub_testsuite) return $sub_testsuite;
		}

		return null;
	}
}
