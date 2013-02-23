<?php
namespace PHPUnitDistributed\PHPUnit;

use PHPUnitDistributed\Util\Witness;
use PHPUnitDistributed\Util\Shell;
use PHPUnitDistributed\DistributedJob\ISlave;

/**
 * Slave that actually runs PHPUnit serially in distributed manner.
 * The construction parameter is itself the parameter array for the PHPUnit Configuration object.
 * The reason why this class is implemented this way, is because the construction parameters
 * are scp'd from the Master to the Slave, and so it must be serializable in PHP.
 */
class RunDistributedSlave implements ISlave
{
	/** @var string[] */
	private $constructor_args;
	/** @var Configuration */
	private $config;
	/** @var bool */
	private $run_serially;

	/**
	 * @param Configuration $config
	 * @param bool $run_serially - run each PHPUnit test in its own PHPUnit invocation
	 */
	public function __construct($config, $run_serially = false)
	{
		$this->constructor_args = func_get_args();
		$this->config = $config;
		$this->run_serially = $run_serially;
	}

	public function get_constructor_args()
	{
		return $this->constructor_args;
	}

	public function run()
	{
		// Validate that there is a test to run
		// In the case that there are more slaves than tests to run, this is a possible non-error case
		if (count($this->config->test_files()) > 0)
		{
			if ($this->run_serially)
			{
				$phpunit_run = new SerialRun($this->config, new Shell(), new Witness());
			}
			else
			{
				$phpunit_run = new Run($this->config, new Shell(), new Witness());
			}

			$phpunit_run->run();
		}
	}
}
