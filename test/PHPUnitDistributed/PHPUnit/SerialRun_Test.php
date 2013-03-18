<?php
namespace PHPUnitDistributed\PHPUnit;

use PHPUnitDistributed\Util\GlobalFunctions;
use PHPUnitDistributed\Util\Shell;
use PHPUnitDistributed\ReflectionHelper;

class SerialRun_Test extends \PHPUnitDistributed\BaseTestCase
{
	public function test_build_phpunit_executors_builds_one_phpunit_run_per_test()
	{
		$config = new Configuration(
			'/somedir/',
			'/results.xml',
			array('/somedir/file1.php', '/somedir/file2.php'),
			array(),
			null,
			false,
			$this->quiet_witness()
		);

		$serial_run = new SerialRun($config, new Shell(), $this->quiet_witness());

		$phpunit_executors = ReflectionHelper::invoke_method_on_object($serial_run, 'build_phpunit_executors');
		$this->assertEquals(count($phpunit_executors), 2, 'There should be two phpunit runners for two test files');

		$first_test_files = $phpunit_executors[0]->config()->test_files();
		$second_test_files = $phpunit_executors[1]->config()->test_files();

		$this->assertEquals(count($first_test_files), 1, 'There should be one test file per phpunit runner');
		$this->assertEquals(count($second_test_files), 1, 'There should be one test file per phpunit runner');

		$this->assertEquals($first_test_files[0], '/somedir/file1.php', 'Test file name for first test is incorrect');
		$this->assertEquals($second_test_files[0], '/somedir/file2.php', 'Test file name for second test is incorrect');
	}

	public function test_run_calls_run_on_all_phpunit_executor()
	{
		$config_mock = $this->shmock('PHPUnitDistributed\PHPUnit\Configuration', function($config) {
			$config->disable_original_constructor();
		});

		$run_mock_1 = $this->create_phpunit_run_mock($config_mock);
		$run_mock_2 = $this->create_phpunit_run_mock($config_mock);

		$serial_run_mock = $this->shmock('PHPUnitDistributed\PHPUnit\SerialRun', function($run) use($run_mock_1, $run_mock_2, $config_mock) {
			$run->disable_original_constructor();
			$run->build_phpunit_executors()->any()->return_value(array($run_mock_1, $run_mock_2));
			$run->config()->any()->return_value($config_mock);
		});

		$this->assertTrue($serial_run_mock->run(), 'The series of runs was successful, so run() should return true');
	}

	private function create_phpunit_run_mock($config_mock)
	{
		return $this->shmock('PHPUnitDistributed\PHPUnit\Run', function($run) use($config_mock) {
			$run->disable_original_constructor();
			$run->run()->once()->return_value(true);
			$run->config()->any()->return_value($config_mock);
		});
	}
}
