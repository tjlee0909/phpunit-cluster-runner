<?php
namespace PHPUnitDistributed\Util;
/**
 * Class to encapsulate global functions involved in shelling out commands
 * NOTE: If we are adding more Windows specializations (such as normalize_shell_exec_command)
 *       we should consider synthesizing Shell into Object Hierarchy with a Factory Pattern.
 */
class Shell
{
	const EXIT_CODE_SUCCESS = 0;

	/*
	* WARNING: This is not the error code guaranteed to be
	* returned by a process. Unless you specifically know the
	* exit code of the target process, do not perform a check
	* like this:
	*
	* exec('cmd', $result, $exit_code);
	* if ($exit_code == Shell::EXIT_CODE_ERROR) die(); // Do your research!
	*
	*/
	const EXIT_CODE_ERROR = 1;

	private $witness;
	private $system_info;

	/**
	* TODO: this is stupid, use a regex.
	*/
	private static $non_windows_cmd_redirects = array (
		'2>/dev/null',
		'2 >/dev/null',
		'2 > /dev/null',
		'2> /dev/null',
		'1>/dev/null',
		'1 >/dev/null',
		'1 > /dev/null',
		'1> /dev/null');

	public function __construct()
	{
		$this->witness = new Witness();
		$this->system_info = new SystemInfo();
	}

	/**
	 * Executes a command, preserving the exit value and the output
	 *
	 * @param string $command A string containing the command.
	 * @param $arg1...$argN varargs to replace inside the command. sprintf will be called on the $command param,
	 * each $arg will have escapeshellarg() called on it before replacement. For example:
	 *
	 * $shell->command('grep -r %s %s', $commit->author(), dirname(__FILE__));
	 *
	 * @return CommandResult containing the output and exit value from executing the command
	 */
	public function command($command)
	{
		$params = func_get_args();
		array_shift($params); // get rid of $command while we escape everything

		$params = array_map(function($param)
		{
			return escapeshellarg($param);
		}, $params);

		array_unshift($params, $command);

		$to_exec = call_user_func_array('sprintf', $params);

		$this->exec($to_exec, $output, $exit);

		return new CommandResult($output, $exit);
	}

	/**
	 * See http://php.net/manual/en/function.exec.php
	 */
	public function exec($command, &$output = null, &$return_var = null)
	{
		$this->witness->log_verbose("$> ". $command);
		$command = $this->normalize_shell_exec_command($command);
		return exec($command, $output, $return_var);
	}

	/**
	 * See http://php.net/manual/en/function.passthru.php
	 * @return CommandResult
	 */
	public function passthru($command)
	{
		$this->witness->log_verbose("$> ". $command);
		$command = $this->normalize_shell_exec_command($command);
		$exit_code = null;
		// the status of the Unix command is returned by reference in passthru
		passthru($command, $exit_code);
		// we have no way to get the output since it was sent to the standard out
		$output = null;
		return new CommandResult($output, $exit_code);
	}

	/**
	 * See http://php.net/manual/en/function.shell-exec.php
	 */
	public function shell_exec($command)
	{
		$this->witness->log_verbose("$> ". $command);
		$command = $this->normalize_shell_exec_command($command);
		return GlobalFunctions::shell_exec($command);
	}

	/**
	 * Full host name including domain: `hostname -f`
	 */
	public function gethostname()
	{
		// Classic PHP!
		// -> On OpenSUSE, gethostname returns only the name of the host
		// -> On Fedora, gethostname returns the host name and domain
		return trim($this->shell_exec('hostname -f'));
	}

	/**
	* Execute the given function in the specified working directory if it is not null,
	* then change back to the prior working directory.
	*/
	public function in_directory($working_dir, $fn)
	{
		if ($working_dir)
		{
			$current = $this->getcwd();
			$this->chdir($working_dir);
			$ret = $fn();
			$this->chdir($current);
			return $ret;
		}
		else
		{
			return $fn();
		}
	}

	/**
	 * http://php.net/manual/en/function.getcwd.php
	 */
	public function getcwd()
	{
		return getcwd();
	}

	/**
	 * See http://php.net/manual/en/function.chdir.php
	 * @param string $directory <p>
	 * The new current directory
	 * @return bool true on success, false on failure
	 */
	public function chdir($directory)
	{
		$this->witness->log_verbose("$> cd $directory");
		return chdir($directory);
	}

	protected function system_info()
	{
		return $this->system_info;
	}

	/**
	 * Normalizes shell execution command strings to work across OS's
	 */
	public function normalize_shell_exec_command($command)
	{
		if (!$this->system_info()->is_windows_os())
		{
			// No-op on non-windows
			return $command;
		}

		// TODO@Murali Change this to regex based search & replace
		foreach (self::$non_windows_cmd_redirects as $redirect)
		{
			$command = str_replace($redirect, '', $command);
		}

		return $command;
	}

	/**
	 * Prompt the user in the shell for a yes/no confirmation.
	 * Created by looking at phutil_console_confirm.
	 */
	public function confirm($prompt, $default_no = true)
	{
		// put the default in uppercase
		$prompt_options = $default_no ? '[y/N]' : '[Y/n]';
		do
		{
			$res = $this->prompt($prompt . ' ' . $prompt_options);
			$res = trim(strtolower($res));
		}
		while ($res != 'y' && $res != 'n' && $res != '');

		$this->passthru_blank_line();

		if ($default_no)
		{
			return ($res == 'y');
		}
		else
		{
			return ($res != 'n');
		}
	}

	/**
	 * Prompt the user in the shell.
	 * Created by looking at phutil_console_prompt.
	 * @TODO (florian by bvanevery) We have a console class in the webapp, we should look at merging the two of these?
	 * It will allow you to prompt for things like passwords.
	 */
	public function prompt($prompt)
	{
		$this->witness()->report($prompt);

		$res = fgets(STDIN);
		return trim(rtrim($res, "\r\n"));
	}

	public function fill_temp_file($prefix, $content)
	{
		$new_tempfile = tempnam(sys_get_temp_dir(), $prefix);

		file_put_contents($new_tempfile, $content);

		return $new_tempfile;
	}

	/**
	 * Display a blank line
	 */
	public function passthru_blank_line()
	{
		// @TODO (florian): move behavior in Windows_Shell and Unix_Shell
		// if more code needs to be added
		if ($this->system_info()->is_windows_os())
		{
			$this->passthru('echo.');
		}
		else
		{
			$this->passthru('echo ' . PHP_EOL);
		}

	}

	/**
	 * Get user name of effective user
	 * More details about effective user versus real {@link http://www.lst.de/~okir/blackhats/node23.html}
	 * @return string User name of effective user
	 */
	public function get_effective_user_name()
	{
		$info = posix_getpwuid(posix_geteuid());
		return $info['name'];
	}
}

