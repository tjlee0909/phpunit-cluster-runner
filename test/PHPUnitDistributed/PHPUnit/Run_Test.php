<?php
namespace PHPUnitDistributed\PHPUnit;

use PHPUnitDistributed\Util\GlobalFunctions;
use PHPUnitDistributed\ReflectionHelper;

class Run_Test extends \PHPUnitDistributed\BaseTestCase
{
	public function test_run_with_valid_config_executes_correct_shell_invocation()
	{
		$phpunit_xml_file_name = '/home/results.xml';
		$app_directory = '/app_dir/';

		$command_result = $this->shmock('PHPUnitDistributed\Util\CommandResult', function($result) {
			$result->disable_original_constructor();
			$result->is_successful()->return_value(true);
		});

		$shell = $this->shmock('PHPUnitDistributed\Util\Shell', function($shell) use($app_directory, $phpunit_xml_file_name, $command_result) {
			$shell->chdir()->any();
			$shell->passthru("phpunit --debug --verbose --configuration $phpunit_xml_file_name")
				->once()
				->return_value($command_result);
		});

		$config_mock = $this->shmock('PHPUnitDistributed\PHPUnit\Configuration', function($config) use($phpunit_xml_file_name)
		{
			$config->disable_original_constructor();
			$config->persist($phpunit_xml_file_name);
			$config->verbose()->any()->return_true();
		});

		$witness = $this->quiet_witness();

		$phpunit_mock = $this->shmock('PHPUnitDistributed\PHPUnit\Run', function($phpunit) use($phpunit_xml_file_name, $config_mock, $app_directory, $shell, $witness)
		{
			$phpunit->set_constructor_arguments($config_mock, $shell, $witness);
			$phpunit->generate_unique_name()->return_value($phpunit_xml_file_name);
		});

		$self = $this;

		GlobalFunctions::stub_function('unlink', function($file) use($phpunit_xml_file_name, $self)
		{
			$self->assertEquals($phpunit_xml_file_name, $file);
		});

		$phpunit_mock->run();
	}
}
