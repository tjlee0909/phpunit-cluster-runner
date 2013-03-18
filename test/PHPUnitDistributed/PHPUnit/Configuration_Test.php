<?php
namespace PHPUnitDistributed\PHPUnit;

use PHPUnitDistributed\Util\GlobalFunctions;
use PHPUnitDistributed\ReflectionHelper;

class Configuration_Test extends \PHPUnitDistributed\BaseTestCase
{
	// Test XML generation
	public function test_files_but_no_directories_no_whitelist_produces_correct_xml()
	{
		$first_test_path = '/test/a_Test.php';
		$second_test_path = '/test/b_Test.php';

		$config = new Configuration(
			'/some/dir/',
			'/junit_path.xml',
			array($first_test_path, $second_test_path),
			array(),
			null,
			false,
			$this->quiet_witness()
		);
		$generated_xml = ReflectionHelper::invoke_method_on_object($config, 'generate_xml');

		$files = $generated_xml->xpath('/phpunit/testsuites/testsuite/file');
		$this->assertEquals(2, count($files), 'Was expecting 2 nodes to be written to testsuites');
		$this->assertEquals($first_test_path, $files[0], "Expected first test file path to be $first_test_path");
		$this->assertEquals($second_test_path, $files[1], "Expected second test file path to be $second_test_path");

		$directories = $generated_xml->xpath('/phpunit/testsuites/testsuite/directory');
		$this->assertEquals(0, count($directories), 'Expected there to be no directories specified in config.');
	}

	public function test_populate_test_files_with_directories_but_no_files()
	{
		$first_directory = '/test/a/';

		$config_mock = $this->shmock('PHPUnitDistributed\PHPUnit\Configuration', function($config) use($first_directory) {
			$config->disable_original_constructor();
			$config->test_names()->any()->return_value(array('/test/1_test.php', '/test/2_test.php'));
		});

		$test_files = ReflectionHelper::invoke_method_on_object(
			$config_mock,
			'populate_test_files',
			array(array(), array($first_directory))
		);

		$this->assertEquals(2, count($test_files), 'Incorrect number of test files returned');
	}

	public function test_populate_test_files_with_directories_and_files()
	{
		$first_directory = '/test/a/';

		$config_mock = $this->shmock('PHPUnitDistributed\PHPUnit\Configuration', function($config) use($first_directory) {
			$config->disable_original_constructor();
			$config->test_names()->any()->return_value(array('/test/1_test.php', '/test/2_test.php'));
		});

		$test_files = ReflectionHelper::invoke_method_on_object(
			$config_mock,
			'populate_test_files',
			array(array('/test/a_Test.php', '/test/b_Test.php'), array($first_directory))
		);

		$this->assertEquals(4, count($test_files), 'Incorrect number of test files returned');
	}

	public function test_bootstrap_file_set_when_specified()
	{
		$config = new Configuration(
			'/some/dir/',
			'/junit_path.xml',
			array('/somedir/1_test.php'),
			array(),
			'/bootstrap.php',
			false,
			$this->quiet_witness()
		);

		$generated_xml = ReflectionHelper::invoke_method_on_object($config, 'generate_xml');

		$this->assertTrue(isset($generated_xml['bootstrap']), 'The bootstrap attribute in <phpunit> should be set to a value');
		$this->assertEquals('/bootstrap.php', $generated_xml['bootstrap'], 'Expected bootstrap attribute to be set to /bootstrap.php');
	}

	public function test_junit_output_path_shows_in_xml_when_specified()
	{
		$config = new Configuration(
			'/some/dir/',
			'/junit_path.xml',
			array('/somedir/1_test.php'),
			array(),
			null,
			false,
			$this->quiet_witness()
		);

		$generated_xml = ReflectionHelper::invoke_method_on_object($config, 'generate_xml');

		$log_nodes = $generated_xml->xpath('/phpunit/logging/log');
		$this->assertEquals(1, count($log_nodes), 'Expected only one log node for junit to exist.');
		$this->assertEquals('junit', $log_nodes[0]['type'], 'Expected log type to be junit');
		$this->assertEquals('/junit_path.xml', $log_nodes[0]['target'], 'Expected log target to be /junit_path.xml');
	}

	// Test persist
	public function test_persist_calls_asxml_on_success()
	{
		$self = $this;
		$witness = $this->quiet_witness();
		$file_name = 'test_results.xml';

		$config_shmock = $this->shmock('PHPUnitDistributed\PHPUnit\Configuration', function($config) use($self, $file_name, $witness)
		{
			$config->set_constructor_arguments(
				'/some/dir/',
				'/junit_path.xml',
				array('/somedir/1_test.php'),
				array(),
				null,
				false,
				$witness
			);

			$xml_mock = $self->shmock('Dummy', function($xml) use($file_name)
			{
				$xml->disable_strict_method_checking();
				$xml->asXML($file_name)->once();
			});

			$config->generate_xml()->once()->return_value($xml_mock);
			$config->populate_test_files()->any()->return_value(array());
		});

		$config_shmock->persist($file_name);
	}
}
