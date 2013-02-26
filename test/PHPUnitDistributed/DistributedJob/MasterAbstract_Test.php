<?php
namespace PHPUnitDistributed\DistributedJob;

use PHPUnitDistributed\Util\Witness;
use PHPUnitDistributed\Util\Shell;
use PHPUnitDistributed\Util\GlobalFunctions;
use PHPUnitDistributed\Util\File;
use PHPUnitDistributed\ReflectionHelper;

class MasterAbstract_Test extends \PHPUnitDistributed\BaseTestCase
{
	public function setUp()
	{
		GlobalFunctions::stub_function('mkdir', function() { });
	}

	public function test_run_sends_jobs_to_two_slaves_for_two_jobs_and_two_hosts()
	{
		$master_shmock = $this->shmock('PHPUnitDistributed\DistributedJob\MasterVanillaImplementation', function($master) {
			$master->slave_hosts()->any()->return_value(array('1','2'));
			$master->slave_jobs()->any()->return_value(array(new \stdClass(), new \stdClass()));
			$master->block_until_slaves_finish()->any();
			$master->copy_result_from_slave()->any();
			$master->delete_file_on_slave()->any();
			$master->send_job_to_slave()->twice();
		});
		$master_shmock->launch($this->quiet_witness());
	}

	public function test_run_sends_one_job_to_one_slave_for_one_job_and_two_hosts()
	{
		$master_shmock = $this->shmock('PHPUnitDistributed\DistributedJob\MasterVanillaImplementation', function($master) {
			$master->slave_hosts()->any()->return_value(array('1','2'));
			$master->slave_jobs()->any()->return_value(array(new \stdClass()));
			$master->block_until_slaves_finish()->any();
			$master->copy_result_from_slave()->any();
			$master->delete_file_on_slave()->any();
			$master->send_job_to_slave()->once();
		});
		$master_shmock->launch($this->quiet_witness());
	}

	public function test_run_exits_for_too_many_jobs_for_slaves()
	{
		$master_shmock = $this->shmock('PHPUnitDistributed\DistributedJob\MasterVanillaImplementation', function($master) {
			$master->slave_hosts()->any()->return_value(array('1'));
			$master->slave_jobs()->any()->return_value(array(new \stdClass(), new \stdClass()));
			$master->send_job_to_slave()->never();
		});
		$master_shmock->launch($this->quiet_witness());
	}

	public function test_result_aggregation_occurs_when_expected_slave_result_is_specified()
	{
		$master_shmock = $this->shmock('PHPUnitDistributed\DistributedJob\MasterVanillaImplementation', function($master) {
			$master->slave_hosts()->any()->return_value(array('1', '2'));
			$master->slave_jobs()->any()->return_value(array(new \stdClass(), new \stdClass()));
			$master->expected_slave_result_file_path()->any()->return_value('/tmp/whatever.xml');
			$master->send_job_to_slave()->any();
			$master->block_until_slaves_finish()->any();
			$master->copy_result_from_slave()->twice()->return_value(true);
			$master->delete_file_on_slave()->any();
		});
		$master_shmock->launch($this->quiet_witness());
	}

	public function test_handle_missing_files_only_called_with_missing_files()
	{
		$master_shmock = $this->shmock('PHPUnitDistributed\DistributedJob\MasterVanillaImplementation', function($master) {
			$master->slave_hosts()->any()->return_value(array('1', '2'));
			$master->slave_jobs()->any()->return_value(array(new \stdClass(), new \stdClass()));
			$master->expected_slave_result_file_path()->any()->return_value('/tmp/whatever.xml');
			$master->send_job_to_slave()->any();
			$master->block_until_slaves_finish()->any();
			$master->copy_result_from_slave()->any()->return_value(false);
			$master->delete_file_on_slave()->any();
			$master->handle_missing_files()->once();
			$master->aggregate_results()->never();
		});
		$master_shmock->launch($this->quiet_witness());
	}

	public function test_aggregate_results_only_called_with_successful_files()
	{
		$master_shmock = $this->shmock('PHPUnitDistributed\DistributedJob\MasterVanillaImplementation', function($master) {
			$master->slave_hosts()->any()->return_value(array('1', '2'));
			$master->slave_jobs()->any()->return_value(array(new \stdClass(), new \stdClass()));
			$master->expected_slave_result_file_path()->any()->return_value('/tmp/whatever.xml');
			$master->send_job_to_slave()->any();
			$master->block_until_slaves_finish()->any();
			$master->copy_result_from_slave()->any()->return_value(true);
			$master->delete_file_on_slave()->any();
			$master->handle_missing_files()->never();
			$master->aggregate_results()->once();
		});
		$master_shmock->launch($this->quiet_witness());
	}

	public function test_result_aggregation_does_not_occur_when_no_output_file_specified()
	{
		$master_shmock = $this->shmock('PHPUnitDistributed\DistributedJob\MasterVanillaImplementation', function($master) {
			$master->slave_hosts()->any()->return_value(array('1', '2'));
			$master->slave_jobs()->any()->return_value(array(new \stdClass(), new \stdClass()));
			$master->expected_slave_result_file_path()->any()->return_value(null);
			$master->send_job_to_slave()->any();
			$master->block_until_slaves_finish()->any();
			$master->copy_result_from_slave()->never();
			$master->delete_file_on_slave()->any();
		});
		$master_shmock->launch($this->quiet_witness());
	}

	public function test_copy_result_from_slave_uses_scp_r_for_directories()
	{
		$file = $this->shmock('PHPUnitDistributed\Util\File', function($file) {
			$file->disable_original_constructor();
			$file->delete_if_exists()->any();
			$file->path()->any()->return_value('some_path.php');
		});

		$command_result = $this->shmock('PHPUnitDistributed\Util\CommandResult', function($result) {
			$result->disable_original_constructor();
			$result->is_successful()->any()->return_true();
		});

		$shell = $this->shmock('PHPUnitDistributed\Util\Shell', function($shell) use($command_result) {
			$shell->passthru('scp -r host:path some_path.php')->once()->return_value($command_result);
		});

		$master_shmock = $this->shmock('PHPUnitDistributed\DistributedJob\MasterVanillaImplementation', function($master) {
			$master->remote_path_is_file()->any()->return_false();
		});

		ReflectionHelper::invoke_method_on_object(
			$master_shmock,
			'copy_result_from_slave',
			array('host', 'path', $file, $shell)
		);
	}
}

class MasterVanillaImplementation extends MasterAbstract
{
	protected function slave_hosts() { return null; }
	protected function slave_jobs($num_slaves) { return null; }
}