<?php
namespace PHPUnitDistributed\PHPUnit;

use PHPUnitDistributed\DistributedJob\ISlave;
use PHPUnitDistributed\Util\Witness;
use PHPUnitDistributed\Util\GlobalFunctions;
use PHPUnitDistributed\Util\File;
use PHPUnitDistributed\Util\Shell;
use PHPUnitDistributed\Util\Rsync;
use PHPUnitDistributed\JUnit\XmlResult;
use PHPUnitDistributed\JUnit\AggregateResult;
use PHPUnitDistributed\DistributedJob\MasterAbstract;
use PHPUnitDistributed\TestDivisionStrategy\IStrategy;
use PHPUnitDistributed\TestDivisionStrategy\TestCount;

/**
 * Master to run PHPUnit serially in a distributed manner.
 * Output of this class is a JUnit xml file.
 */
class RunDistributedMaster extends MasterAbstract implements IRun
{
	/** @var Shell */
	private $shell;
	/** @var Witness */
	private $witness;
	/** @var string[] */
	private $slave_hosts;
	/** @var Configuration */
	private $config;
	/** @var string[] */
	private $rsync_exclude;
	/** @var bool */
	private $run_serially;
	/** @var IStrategy */
	private $test_division_strategy;

	/**
	 * @param Configuration $config - the object that specifies what PHPUnit is going to run on
	 * @param string[] $slave_hosts - list of hosts
	 * @param IStrategy $test_division_strategy [optional] - the test division strategy for dividing these PHPUnit tests
	 * @param string[] $rsync_exclude [optional] - list of $app_directory relative regex items that should not be rsynced from master to slave this argument will be forwarded as --exclude arguments to rsync.
	 * @param bool $run_serially [optional] - should each PHPUnit test be run in its own phpunit execution
	 */
	public function __construct($config, $slave_hosts, $test_division_strategy = null, $rsync_exclude = null, $run_serially = false, $shell = null, $witness = null)
	{
		$this->shell = $shell ?: new Shell();
		$this->witness = $witness ?: new Witness();

		if (!$config)
		{
			$this->witness->log_error('$config is a requried parameter and was not passed in.');
			return;
		}

		$this->config = $config;
		$this->slave_hosts = $slave_hosts;
		$this->test_division_strategy = $test_division_strategy ?: new TestCount();
		$this->rsync_exclude = $rsync_exclude;
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
		$divided_tests = $this->test_division_strategy->divide_tests($num_slaves, $this->config()->test_files());

		$app_dir = File::append_slash_if_not_exists($this->config->app_directory());

		$rsync = new Rsync(
			$this->shell,
			sprintf("%s@%s:%s", $this->shell->get_effective_user_name(), $this->shell->gethostname(), $app_dir),
			$app_dir,
			$this->rsync_exclude,
			'avz'
		);

		$slaves = array();

		foreach ($divided_tests as $tests_for_slave)
		{
			$config_clone = clone $this->config;
			$config_clone->set_test_files($tests_for_slave);
			$config_clone->set_junit_result_output_path($this->expected_slave_result_file_path());
			$slaves[] = new RunDistributedSlave($config_clone, $rsync, $this->run_serially);
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
