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
	 * Note that one of test_files and test_directories is mandatory, not both.
	 *
	 * @param string $app_directory - the absolute path of the top-level project directory (ie: /box/www/current/) to run PHPUnit in
	 * @param string $junit_result_output_path - the absolute path to junit results file. If not specified, won't output one
	 * @param string[] $test_files - the array of PHPUnit test files. Must be absolute paths (ie: /box/www/current/test/php/controllers/Box_Preview_Test.php).
	 * @param string[] $test_directories - the array of directories where _Test.php files live. Must be absolute paths (ie: /box/www/taejun/test/php/controllers), and test files must end in _Test.php.
	 * @param string $bootstrap_file [optional] - defaults to no bootstrap file, the absolute path to the bootstrap PHP file for the PHPUnit run.
	 * @param bool $verbose [optional] - defaults to false, indicates whether PHPUnit should have verbose console output.
	 */
	public function __construct($app_directory, $junit_result_output_path, $test_files, $test_directories, $bootstrap_file = null, $verbose = false, $witness = null)
	{
		$this->witness = $witness ?: new Witness();

		// Validate some params
		if (!$app_directory || !$junit_result_output_path)
		{
			throw new \Exception('Required params app_directory or junit_result_output_path were not passed in.');
		}

		if (count($test_files) == 0 && count($test_directories) == 0)
		{
			throw new \Exception('Param test_files or test_directories are required.');
		}

		$this->app_directory = File::append_slash_if_not_exists($app_directory);
		$this->junit_result_output_path = $junit_result_output_path;
		$this->test_files = $this->populate_test_files($test_files, $test_directories);
		$this->bootstrap_file = $bootstrap_file;
		$this->verbose = $verbose;
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
	/**
	 * Given the specified test files and test directories to run, iterates through the tests in test directories
	 * and adds merges them with the test files array, and returns this array.
	 *
	 * @param string[] $test_files - absolute paths to specific test files to run
	 * @param string[] $test_directories - absolute paths to directories that contain tests to be run
	 */
	protected function populate_test_files($test_files, $test_directories)
	{
		if (!$test_files)
		{
			$test_files = array();
		}

		// Get test files from test directories and add them to test_files
		foreach ($test_directories as $test_directory)
		{
			$tests_in_directory = $this->test_names($test_directory);

			foreach ($tests_in_directory as $test_in_directory)
			{
				$test_files[] = $test_in_directory;
			}
		}

		return array_unique($test_files);
	}

	/**
	 * Get all PHPUnit test paths in $directory.
	 *
	 * @param string $directory
	 * @return string[]
	 */
	protected function test_names($directory)
	{
	    $directory_iterator = new \RecursiveDirectoryIterator($directory);
	    $test_iterator = new \RegexIterator(
	        new \RecursiveIteratorIterator($directory_iterator),
	        '/^.+_Test\.php/',
	        \RecursiveRegexIterator::GET_MATCH
	    );

	    $test_names = array();

	    foreach ($test_iterator as $file)
	    {
	        $test_names[] = $file[0];
	    }

	    return $test_names;
	}
}
