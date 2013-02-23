<?php
namespace PHPUnitDistributed\Util;
/**
 * Encapsulates the shell command result
 */
class CommandResult
{
	private $output;
	private $exit_code;

	public function __construct($output, $exit_code)
	{
		$this->output = $output;
		$this->exit_code = $exit_code;
	}

	public function is_successful()
	{
		return $this->exit_code == Shell::EXIT_CODE_SUCCESS;
	}

	public function is_error()
	{
		return $this->exit_code != Shell::EXIT_CODE_SUCCESS;
	}

	public function output()
	{
		return $this->output;
	}


	public function output_first_line()
	{
		return $this->output[0];
	}

	public function concatenated_output()
	{
		return implode(PHP_EOL, $this->output());
	}

	public function exit_code()
	{
		return $this->exit_code;
	}
}
