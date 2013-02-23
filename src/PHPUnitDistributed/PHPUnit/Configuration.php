<?php
namespace PHPUnitDistributed\PHPUnit;

use PHPUnitDistributed\Util\Shell;
use PHPUnitDistributed\Util\Witness;
use PHPUnitDistributed\Util\GlobalFunctions;
use PHPUnitDistributed\Util\File;

/**
 * Represents a PHPUnit configuration file used to execute PHPUnit.
 *
 * The output of this class is an XML file.
 */
class Configuration
{
	/** @var string */
	private $app_directory;
	/** @var string */
	private $junit_result_output_path;
	/** @var string[] */
	private $test_files;
	/** @var string */
	private $bootstrap_file;
	/** @var bool */
	private $verbose;
	/** @var Witness */
	private $witness;

	/**
	 * @param array $params[
	 *  app_directory => string - the absolute path of the top-level project directory (ie: /box/www/current/) to run PHPUnit in
	 *  junit_result_output_path -> string - the absolute path to junit results file. If not specified, won't output one.
	 *  test_files => string[] - the array of PHPUnit test files. Must be absolute paths (/box/www/current/test/php/controllers/Box_Preview_Test.php).
	 *  OPTIONAL bootstrap_file -> string - defaults to no bootstrap file, the absolute path to the bootstrap PHP file for the PHPUnit run.
	 *  OPTIONAL verbose -> bool - defaults to false, indicates whether PHPUnit should have verbose console output.
	 * ]
	 */
	public function __construct($params, $witness)
	{
		$this->witness = $witness;
        $this->app_directory = File::append_slash_if_not_exists($this->app_directory);

		// Validate required parameters
		$this->app_directory = trim($this->get_param_or_default($params, 'app_directory'));
		$this->junit_result_output_path = $this->get_param_or_default($params, 'junit_result_output_path');
        $this->test_files = isset($params['test_files']) ? $params['test_files'] : null;

		if (!$this->app_directory || !$this->junit_result_output_path || !$this->test_files)
		{
			$this->witness->log_error('Required params app_directory, junit_result_output_path, or test_files were not passed in.');
			return;
		}

		// Set default values for optional parameters
		$this->bootstrap_file = $this->get_param_or_default($params, 'bootstrap_file');
		$this->verbose = $this->get_param_or_default($params, 'verbose', false);
	}

	/**
	 * Generate and save the PHPUnit configuration file.
	 * @param string $file_name - absolute path to the to-be generated PHP configuration file.
	 */
	public function persist($file_name)
	{
		$generated_xml = $this->generate_xml();

		if (!$generated_xml)
		{
			$this->witness->log_error('Failed to generate PHPUnit configuration file.');
			return;
		}

		$generated_xml->asXML($file_name);
	}

	/**
	 * Build the PHPUnit configuration XML from constructor arguments.
	 * @return \SimpleXMLElement - the generated PHPUnit configuration as xml
	 */
	protected function generate_xml()
	{
		$phpunit = new \SimpleXMLElement('<phpunit/>');

		// Set boostrap file if specified
		if ($this->bootstrap_file)
		{
			$phpunit->addAttribute('bootstrap', $this->bootstrap_file);
		}

		$testsuites = $phpunit->addChild('testsuites');
		$testsuite = $testsuites->addChild('testsuite');

		// Add test files to testsuites if exists
		if ($this->test_files && count($this->test_files) > 0)
		{
			foreach ($this->test_files as $file)
			{
				$testsuite->addChild('file', $file);
			}
		}

		// Add logging
		$logging = $phpunit->addChild('logging');

		if ($this->junit_result_output_path())
		{
			$junit_logging = $logging->addChild('log');
			$junit_logging->addAttribute('type', 'junit');
			$junit_logging->addAttribute('target', $this->junit_result_output_path);
			$junit_logging->addAttribute('logIncompleteSkipped', 'false');
		}

		return $phpunit;
	}

	// Some getters and setters
	public function app_directory()
	{
		return $this->app_directory;
	}

	public function set_test_files($test_files)
	{
		$this->test_files = $test_files;
	}

	public function test_files()
	{
		return $this->test_files;
	}

	public function set_junit_result_output_path($path)
	{
		$this->junit_result_output_path = $path;
	}

	public function junit_result_output_path()
	{
		return $this->junit_result_output_path;
	}

	public function set_bootstrap_file($path)
	{
		$this->bootstrap_file = $path;
	}

	public function bootstrap_file()
	{
		return $this->bootstrap_file;
	}

	public function verbose()
	{
		return $this->verbose;
	}

	// Helpers
	private function get_param_or_default($params, $key, $default_value = null)
	{
		return isset($params[$key]) ? $params[$key] : $default_value;
	}
}
