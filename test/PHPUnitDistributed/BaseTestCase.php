<?php
namespace PHPUnitDistributed;

use PHPUnitDistributed\Util\GlobalFunctions;

// Hopefully root out any usage of PHP bad parts

class BaseTestCase extends \PHPUnit_Framework_TestCase
{
	protected $di; // Diesel
	public static $original_registry;  // initialized after class

	/**
	 * Called automatically by PHP before tests are run
	 */
	protected function setUp()
	{
		// Invoke any set up defined on child test
		// Allows us to ensure __this__.setUp is always run regardless of child
		if (method_exists($this, 'set_up')) $this->set_up();
	}

	/**
	 * Called automatically by PHP after tests are run
	 */
	protected function tearDown()
	{
		GlobalFunctions::reset_stubs();
		// Invoke any tear down defined on child test
		// Allows us to ensure __this__.tearDown is always run regardless of child
		if (method_exists($this, 'tear_down')) $this->tear_down();
	}

	/*
	 * public for nesting
	 */
	public function shmock($class_name, $conf_closure)
	{
		return Shmock::create($this, $class_name, $conf_closure);
	}

	public function shmock_class($class_name, $conf_closure)
	{
		return Shmock::create_class($this, $class_name, $conf_closure);
	}

	/**
	 * Assert that $anonFunc fails with Exception of $type
	 * @param \Closure $failingClosure (PHPUnit) => () Anonymous function containing code expected to fail
	 */
	protected function assert_error($type, $msgNeedle, $failingClosure)
	{
		try
		{
			$failingClosure($this);
			$this->fail("Expected exception, but succeeded. Type $type with $msgNeedle");
		}
		catch (\Exception $e)
		{
			if (strstr(get_class($e), 'PHPUnit'))
			{
				throw $e;
			}

			$this->assertInstanceOf($type, $e, 'Expected exception class type');
			$this->assertContains($msgNeedle, $e->getMessage(), 'Expected exception message');
		}
	}

	protected function mock_global_function($fn_name, $fn_closure)
	{
		// make shmock of global mocker dummy object
		$shmock = $this->shmock('Global_Mocker', $fn_closure);

		// the closure below will get called whenever the global function is
		// called, which calls the 'run' method on the dummy shmock object
		// with the passed in arguments

		$closure = function() use ($shmock)
		{
			$args = func_get_args();
			return call_user_func_array(array($shmock, "run"), $args);
		};
		GlobalFunctions::stub_function($fn_name, $closure);
	}

	protected function quiet_witness()
	{
		return $this->shmock('PHPUnitDistributed\Util\Witness', function($witness) {
			$witness->disable_original_constructor();
			$witness->report()->any();
		});
	}
}

class Dummy
{
    public function __call($name, $args){}
}

class Global_Mocker
{
	public function run (){}
}
