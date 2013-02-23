<?php
namespace PHPUnitDistributed\PHPUnit;

use PHPUnitDistributed\Util\Shell;
use PHPUnitDistributed\Util\Witness;
use PHPUnitDistributed\Util\GlobalFunctions;

/**
 * Run PHPUnit with $this->config() PHPUnit configuration settings, launching all of the
 * specified tests in a single command-line phpunit invocation.
 */
class Run implements IRun
{
	/** @var Configuration */
	private $config;
	/** @var Shell */
	private $shell;
	/** @var Witness */
	private $witness;
	/** @var string */
	private $output_file;

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
		$phpunit_xml_file_name = $this->generate_unique_name('.xml');
		$this->config()->persist($phpunit_xml_file_name);
		$shell = $this->shell;
		$output_file = $this->output_file;
		$verbose = $this->config()->verbose();

		$phpunit_execution_result = $this->shell->in_directory(
            $this->config()->app_directory(),
			function() use($shell, $phpunit_xml_file_name, $output_file, $verbose)
			{
				$verbose_command_options = $verbose ? '--debug --verbose' : '';
				return $shell->passthru("phpunit $verbose_command_options --configuration $phpunit_xml_file_name");
			}
        );

		// Cleanup configuration file
		GlobalFunctions::unlink($phpunit_xml_file_name);

		return $phpunit_execution_result->is_successful();
	}

	public function config()
	{
		return $this->config;
	}

	/**
	 * Generates a unique name to store the PHPUnit configuration files or directories.
	 *
	 * @param string $extension - the extension of the file
	 * @param string $additional_token - an additional string to have inserted in the middle of the unique token
	 * @return string - the absolute path of the to-be or already generated XML config
	 */
	protected function generate_unique_name($extension = '.xml', $additional_token = '')
	{
		return GlobalFunctions::sys_get_temp_dir() . '/phpunit_' . time() . "$additional_token$extension";
	}
}
