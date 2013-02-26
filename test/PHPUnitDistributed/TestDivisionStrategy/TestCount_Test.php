<?php
namespace PHPUnitDistributed\TestDivisionStrategy;

use PHPUnitDistributed\Util\GlobalFunctions;

class TestCount_Test extends \PHPUnitDistributed\BaseTestCase
{
	public function test_divide_tests_evenly_for_two_slaves_and_two_files()
	{
		$test_file_contents = $this->build_test_file_content_with_n_tests(2);
		GlobalFunctions::stub_function('file_get_contents', function() use($test_file_contents) {
			return $test_file_contents;
		});

		$test_files = $this->get_n_test_files(2);

		$division_strat = new TestCount();
		$divided_tests = $division_strat->divide_tests(2, $test_files);

		$this->assertCount(2, $divided_tests, 'Tests not divided to the correct number of slaves');
	}

	public function test_divide_tests_for_many_slaves_and_many_files_produces_correct_groupings()
	{
		$test_file_contents = $this->build_test_file_content_with_n_tests(2);
		GlobalFunctions::stub_function('file_get_contents', function() use($test_file_contents) {
			return $test_file_contents;
		});

		$test_files = $this->get_n_test_files(20);

		$division_strat = new TestCount();
		$divided_tests = $division_strat->divide_tests(3, $test_files);

		$this->assertCount(3, $divided_tests, 'Tests not divided to the correct number of slaves');

		$tests = 0;
		foreach ($divided_tests as $tests_per_slave)
		{
			$tests += count($tests_per_slave);
		}

		$this->assertEquals(20, $tests, 'Total number of tests split does not equal to original number');
	}

	private function build_test_file_content_with_n_tests($num_tests)
	{
		$test_content = '';

		for ($i = 0; $i < $num_tests; $i++)
		{
			$test_content .= "public function test_something_test() { }\n\n";
		}

		return $test_content;
	}

	private function get_n_test_files($num_files)
	{
		$files = array();

		for ($i = 0; $i < $num_files; $i++)
		{
			$files[] = '/file' . $i . '_Test.php';
		}

		return $files;
	}
}
