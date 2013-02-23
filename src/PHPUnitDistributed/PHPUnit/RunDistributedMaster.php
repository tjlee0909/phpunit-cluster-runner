<?php
namespace PHPUnitDistributed\PHPUnit;

use PHPUnitDistributed\DistributedJob\ISlave;
use PHPUnitDistributed\Util\Witness;
use PHPUnitDistributed\Util\GlobalFunctions;
use PHPUnitDistributed\Util\File;
use PHPUnitDistributed\JUnit\XmlResult;
use PHPUnitDistributed\JUnit\AggregateResult;
use PHPUnitDistributed\DistributedJob\MasterAbstract;

/**
 * Master to run PHPUnit serially in a distributed manner.
 * Output of this class is a JUnit xml file.
 */
class RunDistributedMaster extends MasterAbstract implements IRun
{
	/** @var Witness */
	private $witness;
	/** @var string[] */
	private $slave_hosts;
	/** @var Configuration */
	private $config;
	/** @var bool */
	private $run_serially;

	/**
	 * @param Configuration $config - the object that specifies what PHPUnit is going to run on
	 * @param string[] $slave_hosts - list of hosts
	 * @param Witness $witness
	 */
	public function __construct($config, $slave_hosts, $witness, $run_serially = false)
	{
		$this->witness = $witness;

		if (!$config)
		{
			$this->witness->log_error('$config is a requried parameter and was not passed in.');
			return;
		}

		$this->config = $config;
		$this->slave_hosts = $slave_hosts;
		$this->run_serially = $run_serially;
	}

	// The implemented abstract methods for IRun
	public function run()
	{
		// Kick off the distributed job!
		$this->launch();
		return true;
	}

	public function config()
	{
		return $this->config;
	}

	// The implemented abstract methods for MasterAbstract
	protected function slave_hosts()
	{
		return $this->slave_hosts;
	}

	protected function slave_jobs($num_slaves)
	{
		$division_strategy = new \PHPUnitDistributed\TestDivisionStrategy\TestCount();
		$divided_tests = $division_strategy->divide_tests($num_slaves, $this->config()->test_files());

		$slaves = array();

		foreach ($divided_tests as $tests_for_slave)
		{
			$config_clone = clone $this->config;
			$config_clone->set_test_files($tests_for_slave);
			$config_clone->set_junit_result_output_path($this->expected_slave_result_file_path());

			$slaves[] = new RunDistributedSlave($config_clone, $this->run_serially);
		}

		return $slaves;
	}

	protected function expected_slave_result_file_path()
	{
		return GlobalFunctions::sys_get_temp_dir() . '/phpunit_serial_junit_result.xml';
	}

	protected function aggregate_results($result_file_paths)
	{
		$junit_results = array();

		foreach ($result_file_paths as $result_file_path)
		{
			$junit_results[] = new XmlResult(new File($result_file_path), $this->witness);
		}

		$aggregate_result = new AggregateResult($junit_results, $this->witness);
		$aggregate_result->xml()->asXML($this->config->junit_result_output_path());
	}
}
