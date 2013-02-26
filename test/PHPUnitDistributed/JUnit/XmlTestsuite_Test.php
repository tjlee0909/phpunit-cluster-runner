<?php
namespace PHPUnitDistributed\JUnit;

class XmlTestsuite_Test extends \PHPUnitDistributed\BaseTestCase
{
	public function test_file_returns_parsed_attribute()
	{
		$testsuite = $this->get_testsuite_with_xml('<testsuite file=\'SomeValue\' />');
		$this->assertEquals('SomeValue', $testsuite->file(), 'Incorrectly parsed file attribute');
	}

	public function test_time_returns_parsed_attribute()
	{
		$testsuite = $this->get_testsuite_with_xml('<testsuite time=\'1.23\' />');
		$this->assertEquals(1.23, $testsuite->time(), 'Incorrectly parsed time attribute');
	}

	public function test_tests_returns_parsed_attribute()
	{
		$testsuite = $this->get_testsuite_with_xml('<testsuite tests=\'32\' />');
		$this->assertEquals(32, $testsuite->tests(), 'Incorrectly parsed tests attribute');
	}

	public function test_errors_returns_parsed_attribute()
	{
		$testsuite = $this->get_testsuite_with_xml('<testsuite errors=\'2\' />');
		$this->assertEquals(2, $testsuite->errors(), 'Incorrectly parsed errors attribute');
	}

	public function test_failures_returns_parsed_attribute()
	{
		$testsuite = $this->get_testsuite_with_xml('<testsuite failures=\'5\' />');
		$this->assertEquals(5, $testsuite->failures(), 'Incorrectly parsed failures attribute');
	}

	private function get_testsuite_with_xml($xml)
	{
		return new XmlTestsuite(simplexml_load_string($xml));
	}
}
