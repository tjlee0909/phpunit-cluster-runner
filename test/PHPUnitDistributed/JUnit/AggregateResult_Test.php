<?php
namespace PHPUnitDistributed\JUnit;

use PHPUnitDistributed\Util\GlobalFunctions;

class AggregateResult_Test extends \PHPUnitDistributed\BaseTestCase
{
	public function test_xml_generates_one_testsuites_node()
	{
		$result_mocks = $this->get_many_junit_result_mocks(
			1,
			'<testsuites>
				<testsuite />
				<testsuite />
			</testsuites>'
		);
		$aggr_result = new AggregateResult($result_mocks, $this->quiet_witness());
		$testsuites_nodes = $aggr_result->xml()->xpath('/testsuites');

		$this->assertCount(1, $testsuites_nodes, 'Expected there to be only one testsuites node');
	}

	public function test_xml_generates_multiple_testsuite_nodes_for_multiple_junit_results()
	{
		$result_mocks = $this->get_many_junit_result_mocks(
			10,
			'<testsuites>
				<testsuite file="somefile.php" />
			</testsuites>'
		);
		$aggr_result = new AggregateResult($result_mocks, $this->quiet_witness());
		//var_dump($aggr_result->xml());
		$testsuites_nodes = $aggr_result->xml()->xpath('/testsuites/testsuite');

		$this->assertCount(10, $testsuites_nodes, 'Expected there to be only one testsuites node');
	}

	private function get_many_junit_result_mocks($num_to_make, $xml)
	{
		$result_mocks = array();

		for ($i = 0; $i < $num_to_make; $i++)
		{
			$result_mocks[] = $this->get_junit_result_mock($xml);
		}

		return $result_mocks;
	}

	/**
	 * @param string $xml
	 */
	private function get_junit_result_mock($xml)
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

