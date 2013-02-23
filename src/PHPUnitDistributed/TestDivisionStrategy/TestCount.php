<?php
namespace PHPUnitDistributed\TestDivisionStrategy;

use PHPUnitDistributed\Util\GlobalFunctions;

/**
 * Test division strategy where we try to give each slave close-to-equal number of tests.
 * The algorithm here is a zig-zag round-robinning of the PHPUnit test files with the most tests
 * to least being distributed to each of the slaves.
 */
class TestCount implements IStrategy
{
    public function divide_tests($num_slaves, $test_files)
    {
		$slave_to_test = array();

		/** @var string[int] $test_paths_to_num_tests - key: path to the test, value: num tests in file */
		$test_paths_to_num_tests = array();

		foreach ($test_files as $test_file)
		{
			$test_paths_to_num_tests[$test_file] = substr_count(
				GlobalFunctions::file_get_contents($test_file),
				'public function test'
			);
		}

		// Sort tests in order of descending number of tests
		arsort($test_paths_to_num_tests);

		for ($i = 0; $i < $num_slaves; $i++)
		{
			$slave_to_test[] = array();
		}

		// Zig-zag round-robin drop the files onto the slaves from heaviest to least heaviest
		$forward_direction = true;
		$slave_index = 0;

		foreach (array_keys($test_paths_to_num_tests) as $test_path)
		{
			$slave_to_test[$slave_index][] = $test_path;

			// Zig-zagging operation here
			if ($forward_direction)
			{
				$slave_index++;

				if ($slave_index >= $num_slaves)
				{
					$slave_index = $num_slaves - 1;
					$forward_direction = false;
				}
			}
			else
			{
				$slave_index--;

				if ($slave_index < 0)
				{
					$slave_index = 0;
					$forward_direction = true;
				}
			}
		}

		return $slave_to_test;
    }
}
