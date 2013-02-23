<?php
namespace PHPUnitDistributed\TestDivisionStrategy;

/**
 * Interface for different methods of dividing tests
 */
interface IStrategy
{
	/**
	 * Given a list of tests, will return an array of $num_slaves string-arrays that will contain
	 * the dividided tests (still totalling the same number as the original $test_files parameter)
	 *
	 * @abstract
	 * @param int $num_slaves - number of ways to distribute $tests
	 * @param string[] $test_files - list of absolute paths to tests
	 * @return string[][]
	 */
	public function divide_tests($num_slaves, $test_files);
}
