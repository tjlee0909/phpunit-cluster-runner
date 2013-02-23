<?php
namespace PHPUnitDistributed\JUnit;

use PHPUnitDistributed\Util\Witness;
use PHPUnitDistributed\Util\File;
use PHPUnitDistributed\Util\GlobalFunctions;

/**
 * Aggregates a set of JUnit results into a single result.
 */
class AggregateResult extends ResultAbstract
{
	/** @var ResultAbstract[] */
	private $results;
	/** @var Witness */
	private $witness;
	/** @var \SimpleXMLElement */
	private $aggregate_xml;
	/** @var TestsuiteAbstract[] */
	private $testsuites;

	/**
	 * @param ResultAbstract[] $junit_results - JUnit results to aggregate
	 * @param Witness $witness
	 */
	public function __construct($junit_results, $witness)
	{
		$this->witness = $witness;

		if (!$junit_results || count($junit_results) == 0)
		{
			$this->witness->log_error('Did not specify junit results in aggregate results object.');
			return;
		}

		$this->results = $junit_results;
	}

	public function xml()
	{
		$this->aggregate_testsuites();
		return $this->aggregate_xml;
	}

	public function testsuites()
	{
		$this->aggregate_testsuites();
		return $this->testsuites;
	}

	/**
	 * Aggregates testsuites and populates $this->aggregate_xml and $this->testsuites.
	 */
	protected function aggregate_testsuites()
	{
		if (!$this->aggregate_xml || !$this->testsuites)
		{
			$this->aggregate_xml = new \SimpleXMLElement('<testsuites/>');
			$this->testsuites = array();

			// Iterate through each Result's xml, parsing out the top-level testsuite nodes
			// and appending it to the root testsuites node.
			foreach ($this->results as $result)
			{
				foreach ($result->testsuites() as $testsuite)
				{
					$this->testsuites[] = $testsuite;
					$this->import_xml_node($this->aggregate_xml, $testsuite->xml());
				}
			}
		}
	}

	/**
	 * Import and xml node inside of another
	 *
	 * @param \SimpleXMLElement $to
	 * @param \SimpleXMLElement $from
	 */
	protected function import_xml_node($to, $from)
	{
		$toDom = GlobalFunctions::dom_import_simplexml($to);
		$fromDom = GlobalFunctions::dom_import_simplexml($from);
		$toDom->appendChild($toDom->ownerDocument->importNode($fromDom, true));
	}
}