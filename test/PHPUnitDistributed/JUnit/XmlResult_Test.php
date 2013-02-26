<?php
namespace PHPUnitDistributed\JUnit;

use PHPUnitDistributed\Util\GlobalFunctions;

class XmlResult_Test extends \PHPUnitDistributed\BaseTestCase
{
	public function test_testsuites_returns_correct_count_for_one_testsuite()
	{
		$result = $this->get_result_with_xml('<testsuites><testsuite file="somefile.php"></testsuite></testsuites>');
		$this->assertCount(1, $result->testsuites(), 'Incorrect number of testsuites parsed');
	}

	public function test_testsuites_returns_correct_count_for_many_testsuites()
	{
		$result = $this->get_result_with_xml(
			'<testsuites>
				<testsuite file="somefile.php" />
				<testsuite file="somefile.php" />
				<testsuite file="somefile.php" />
				<testsuite file="somefile.php" />
			</testsuites>'
		);
		$this->assertCount(4, $result->testsuites(), 'Incorrect number of testsuites parsed');
	}

	public function test_attributes_in_testsuite_are_properly_parsed_when_nested()
	{
		$result = $this->get_result_with_xml(
			'<testsuites>
				<testsuite>
					<testsuite file="some_value.php" />
				</testsuite>
			</testsuites>'
		);
		$this->assertCount(1, $result->testsuites(), 'Incorrect number of testsuites parsed');

		$testsuites = $result->testsuites();
		$this->assertEquals('some_value.php', $testsuites[0]->file(), 'Incorrect file');
	}

	private function get_result_with_xml($xml)
	{
		GlobalFunctions::stub_function('simplexml_load_file', function() use($xml) {
			return simplexml_load_string($xml);
		});

		$file_mock = $this->shmock('PHPUnitDistributed\Util\File', function($file) {
			$file->disable_original_constructor();
			$file->exists()->any()->return_true();
		});

		return new XmlResult($file_mock, $this->quiet_witness());
	}
}
