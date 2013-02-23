<?php
namespace PHPUnitDistributed\PHPUnit;

use PHPUnitDistributed\Util\Shell;
use PHPUnitDistributed\Util\Witness;
use PHPUnitDistributed\Util\GlobalFunctions;
use PHPUnitDistributed\Util\File;
use PHPUnitDistributed\JUnit\XmlResult;
use PHPUnitDistributed\JUnit\AggregateResult;

/**
 * Daisy chains multiple PHPUnit runs and aggregates the results into JUnit xml format.
 *
 * For each test file that is passed in here, each test file is run individually. This is done to ensure
 * independence of each test class from one another, and in some test environments have shown to even
 * perform better.
 */
class SerialRun implements IRun
{
	/** @var IRun[] */
	private $phpunit_executors;
	/** @var Configuration */
	private $config;

	/**
	 * @param Configuration $config - the object that specifies what PHPUnit is going to run on
	 * @param Shell $shell
	 * @param Witness $witness
	 * @param (OPTIONAL) string $output_file - absolute path to file to write PHPUnit console output to
	 */
	public function __construct($config, $shell, $witness, $output_file = null)
	{
		$this->witness = $witness;
		$this->shell = $shell;

		if (!$config)
		{
			$this->witness->log_error('$config is a requried parameter and was not passed in.');
			return;
		}

		$this->config = $config;
		$this->output_file = $output_file;
	}

	public function run()
	{
		$succeeded = true;
		/** @var string[] => string $junit_result_files - test file path to JUnit result file path mapping */
		$junit_result_files = array();
		$junit_result_path = $this->config()->junit_result_output_path();

		// Execute PHPUnit tests and gather the locations of the results
		/** @var Run $phpunit_executor */
		foreach ($this->build_phpunit_executors() as $phpunit_executor)
		{
			$succeeded = $phpunit_executor->run() && $succeeded;
			$test_files = $phpunit_executor->config()->test_files();

			if ($phpunit_executor->config()->junit_result_output_path())
			{
				$junit_result_files[$test_files[0]] = $phpunit_executor->config()->junit_result_output_path();
			}
		}

		// Aggregate results and clean up temporary files
		if ($junit_result_path && count($junit_result_files) > 0)
		{
			$this->aggregate_and_cleanup_junit($junit_result_path, $junit_result_files);
		}

		return $succeeded;
	}

	public function config()
	{
		return $this->config;
	}

	/**
	 * For each test file specified, creates a Run instance with one test file to execute.
	 *
	 * @return IRUN[]
	 */
	protected function build_phpunit_executors()
	{
		if (!$this->phpunit_executors)
		{
			$junit_result_path = $this->config()->junit_result_output_path();
			$this->phpunit_executors = array();
			$i = 0;

			foreach ($this->config()->test_files() as $file)
			{
				// We want the individual Run instances to have its own copy of the config object
				// because these values are going to be slightly different per instance
				$config_clone = clone $this->config();

				// These paths are going to be different from this instance's paths
				$config_clone->set_junit_result_output_path(null);

				// Only want one test file to be run for each PHPUnit executor
				$config_clone->set_test_files(array($file));

				// Have each of the PHPUnit_Run's write results to a temporary location if we want them at all
				if (!empty($junit_result_path))
				{
					$config_clone->set_junit_result_output_path($this->generate_unique_name('.xml', $i . '_junit'));
				}

				$this->phpunit_executors[] = new Run($config_clone, $this->shell, $this->witness, $this->output_file);
				$i++;
			}
		}

		return $this->phpunit_executors;
	}

	/**
	 * Combines JUnit results into a single JUnit-format XML file and delete temporary
	 * JUnit files that were created in the process.
	 *
	 * @param string $aggregate_junit_path - absolute path to write final JUnit result to
	 * @param string[] $individual_junit_paths - absolute paths to individual JUnit results to
	 */
	private function aggregate_and_cleanup_junit($aggregate_junit_path, $individual_junit_paths)
	{
		$junit_results = array();

		foreach ($individual_junit_paths as $test_file => $result_path)
		{
			// If something went wrong with the generation of the JUnit result file, consider it a fatal
			// and generate a default JUnit result file with an error
			$file = new File($result_path);

			if (!$file->exists() || $file->is_empty())
			{
				$this->witness->log_error($file->path() . " for test $test_file either doesn't exist or is empty\n");
				$file->delete_if_exists();

				// Create an empty junit result and add to junit_results
				$manual_result_xml = $this->build_result_with_single_failure_and_message("Failed to find JUnit result for test file $test_file", $test_file);
				$manual_result_xml->asXML($file->path());
			}

			$junit_results[] = new XmlResult($file, $this->witness);
		}

		$aggregate_result_file = new File($aggregate_junit_path);
		$aggregate_result_file->delete_if_exists();

		$aggregate_junit_result = new AggregateResult($junit_results, $this->witness);
		$aggregate_junit_result->xml()->asXML($aggregate_result_file->path());

		foreach ($individual_junit_paths as $result_path)
		{
			$file = new File($result_path);
			$file->delete_if_exists();
		}
	}

	/**
	 * Generate a \SimpleXmlElement with a failed testcase.
	 *
	 * @param string $message
	 * @param string $test_file
	 * @return \SimpleXmlElement
	 */
	private function build_result_with_single_failure_and_message($message, $test_file)
	{
		// I know this looks ridiculous, but you have to go this many nested elements deep to
		// produce XML that represents a failure for a made-up test.
		$testsuites = new \SimpleXMLElement('<testsuites/>');

		$testsuite = $testsuites->addChild('testsuite');
		$testsuite->addAttribute('name', $test_file);
		$testsuite->addAttribute('tests', 1);
		$testsuite->addAttribute('failures', 1);
		$testsuite->addAttribute('assertiosn', 0);
		$testsuite->addAttribute('time', 0);

		$testcase = $testsuite->addChild('testcase');
		$testcase->addAttribute('file', $test_file);
		$testcase->addAttribute('name', 'Fatal_Exception');

		$failure = $testcase->addChild('failure', $message);
		$failure->addAttribute('type', 'Fatal_Exception');

		return $testsuites;
	}

	/**
	 * Generates a unique name to store the PHPUnit configuration files or directories.
	 *
	 * @param string $extension - the extension of the file
	 * @param string $additional_token - an additional string to have inserted in the middle of the unique token
	 * @return string - the absolute path of the to-be or already generated XML config
	 */
	private function generate_unique_name($extension = '.xml', $additional_token = '')
	{
		return GlobalFunctions::sys_get_temp_dir() . '/phpunit_' . time() . "$additional_token$extension";
	}
}
