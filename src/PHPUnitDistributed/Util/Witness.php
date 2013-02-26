<?php
namespace PHPUnitDistributed\Util;
/**
 * Really bare facility to output messages to the console.
 */
class Witness
{
	public function log_verbose($msg)
	{
        $this->report($msg, 'green');
	}

	public function log_information($msg)
	{
        $this->report($msg, 'cyan');
	}

	public function log_warning($msg)
	{
        $this->report($msg, 'yellow');
	}

	public function log_error($msg)
	{
        $this->report($msg, 'red');
	}

	/**
	 * Echo $msg in $color
	 * @param bool $eol - print end of line
	 */
	protected function report($msg = '', $color = null, $eol = true)
	{
		$msg = $this->format($msg, $color, $eol);
		echo $msg;
		flush();
		@ob_flush(); // NOTE using @ to suppress PHP warnings when no buffers to flush
	}

	private function format($msg, $color, $eol)
	{
		if ($color)
		{
			$msg = EscapeColors::fg_color($color, $msg);
		}
		$msg = $eol ? $msg . PHP_EOL : $msg;
		return $msg;
	}
}
