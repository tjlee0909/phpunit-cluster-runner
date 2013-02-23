<?php
namespace PHPUnitDistributed\JUnit;
/**
 * Represents a testsuite generated from a JUnit xml file
 */
class XmlTestsuite extends TestsuiteAbstract
{
	/** @var \SimpleXMLElement */
	private $testsuite_xml;

	/**
	 * @param \SimpleXMLElement $testsuite_xml
	 */
	public function __construct($testsuite_xml)
	{
		$this->testsuite_xml = $testsuite_xml;
	}

	public function xml()
	{
		return $this->testsuite_xml;
	}

	public function file()
	{
		return $this->parse_attribute_from_node($this->testsuite_xml, 'file', 'strval');
	}

	public function time()
	{
		return $this->parse_attribute_from_node($this->testsuite_xml, 'time', 'floatval');
	}

	public function tests()
	{
		return $this->parse_attribute_from_node($this->testsuite_xml, 'tests', 'intval');
	}

	public function errors()
	{
		return $this->parse_attribute_from_node($this->testsuite_xml, 'errors', 'intval');
	}

	public function failures()
	{
		return $this->parse_attribute_from_node($this->testsuite_xml, 'failures', 'intval');
	}

	private function parse_attribute_from_node($node, $field, $type)
	{
		if (isset($node[$field]))
		{
			return $type($node[$field]);
		}

		return null;
	}

}
