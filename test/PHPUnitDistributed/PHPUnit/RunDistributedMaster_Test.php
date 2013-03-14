<?php
namespace PHPUnitDistributed\PHPUnit;

use PHPUnitDistributed\Util\Shell;
use PHPUnitDistributed\ReflectionHelper;
use PHPUnitDistributed\DistributedJob\ISlave;

class RunDistributedMaster_Test extends \PHPUnitDistributed\BaseTestCase
{
	public function test_slave_jobs_returns_jobs_that_implements_slave_interface()
	{
		$shell = $this->shmock('PHPUnitDistributed\Util\Shell', function($shell) {
			$shell->disable_original_constructor();
			$shell->get_effective_user_name()->any()->return_value('user');
			$shell->gethostname()->any()->return_value('host');
		});

		$test_division_strat = $this->shmock('PHPUnitDistributed\TestDivisionStrategy\TestCount', function($strat) {
			$strat->disable_original_constructor();
			$strat->divide_tests()->
					any()->
					return_value(array(array('test1.php')));
		});

		$witness = $this->quiet_witness();

		$config_mock = $this->shmock('PHPUnitDistributed\PHPUnit\Configuration', function($config) {
			$config->disable_original_constructor();
			$config->test_files()->any()->return_value(array());
		});

		$master = $this->shmock('PHPUnitDistributed\PHPUnit\RunDistributedMaster',
			function($master) use($test_division_strat, $witness, $config_mock, $shell) {
				$master->set_constructor_arguments(
					$config_mock,
					array('slave1'),
					$test_division_strat,
					$shell,
					$witness
				);
			}
		);
		$slave_jobs = ReflectionHelper::invoke_method_on_object($master, 'slave_jobs', array(1));

		$this->assertCount(1, $slave_jobs, 'Incorrect number of slaves returned');
		$this->assertTrue($slave_jobs[0] instanceof ISlave, 'Returned slave does not implement ISlave');
	}

	public function test_expected_slave_result_file_path_is_set()
	{
		$master = new RunDistributedMaster(new \stdClass(), array(), null, null, null);
		$expected_slave_result_file_path = ReflectionHelper::invoke_method_on_object($master, 'expected_slave_result_file_path');

		$this->assertTrue(strlen($expected_slave_result_file_path) > 0, 'expected_slave_result_file_path should be implemented for this job');
	}
}
