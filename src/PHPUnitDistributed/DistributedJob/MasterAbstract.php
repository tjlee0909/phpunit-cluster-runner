<?php
namespace PHPUnitDistributed\DistributedJob;

use PHPUnitDistributed\Util\Witness;
use PHPUnitDistributed\Util\Shell;
use PHPUnitDistributed\Util\GlobalFunctions;
use PHPUnitDistributed\Util\File;

/**
 * A genericized cluster runner master. In the master-slave relationship, the master is responsible for
 * the two following core tasks:
 *
 * 1). Distributing the load amongst slaves. In this class, this is done by instantiating the appropriate
 *  number of ISlave instances and make sure that these instances get properly unserialized
 *  and instantiated on the slave hosts.
 *
 * and
 *
 * 2). Aggregating results when all slaves are finished (if there are any results to aggregate). Note that
 *  some jobs won't require any result file aggregation, because the work that is done will have results
 *  that are persisted in some database instead of a file.
 */
abstract class MasterAbstract
{
	/** @var int */
	private $timestamp_for_this_run;
	/** @var Witness */
	private $witness;
	/** @var Shell */
	private $shell;

	/**
	 * Run it!
	 *
	 * @param Witness $witness
	 */
	public function launch($witness = null)
	{
		$this->witness = $witness ?: new Witness();
		$this->shell = new Shell();
		$this->timestamp_for_this_run = GlobalFunctions::time();

		$slave_hosts = $this->slave_hosts();
		$slave_jobs = $this->slave_jobs(count($slave_hosts));

		// 1). Validate parameters
		$this->witness->log_information('Validating parameters...');

		if (count($slave_jobs) > count($slave_hosts))
		{
			$this->witness->log_error('Error: more slave jobs (' . count($slave_jobs) . ') were specified than hosts (' . count($slave_hosts) . '). Stopping execution.');
			return;
		}
		if (count($slave_jobs) < count($slave_hosts))
		{
			$this->witness->log_warning('Warning: fewer slave jobs (' . count($slave_jobs) . ') were specified than hosts (' . count($slave_hosts) . '). Slaves will be under-utilized.');

			// Update the slave hosts to only include the slaves that will be utilized
			$slave_hosts = array_slice($slave_hosts, 0, count($slave_jobs));
		}

		// 2). Launch slave jobs
		$this->witness->log_information('Launching slave jobs...');
		$executed_commands = array();

		for ($i = 0; $i < count($slave_jobs); $i++)
		{
			$executed_commands[] = $this->send_job_to_slave($slave_jobs[$i], $slave_hosts[$i]);
		}

		// 3). Wait for slaves to finish
		$this->witness->log_information('Waiting for slave jobs to finish...');
		$this->block_until_slaves_finish($executed_commands, $slave_hosts);
		$this->witness->log_information('Slave jobs complete.');

		// Check if we care about result files from slaves
		$expected_slave_result_file_path = trim($this->expected_slave_result_file_path());

		if (!empty($expected_slave_result_file_path))
		{
			$results_paths = array();
			$slaves_with_missing_results = array();
			$results_directory = $this->temp_directory() . '/distributed_' . $this->timestamp_for_this_run . '_job';
			GlobalFunctions::mkdir($results_directory);

			// 4). Copy results to master
			foreach ($slave_hosts as $slave_host)
			{
				$local_path = $results_directory . '/' . $slave_host . '_result';

				$successfully_copied = $this->copy_result_from_slave(
					$slave_host,
					$expected_slave_result_file_path,
					new File($local_path),
					$this->shell
				);

				if ($successfully_copied)
				{
					$results_paths[] = $local_path;
					$this->delete_file_on_slave($slave_host, $expected_slave_result_file_path);
				}
				else
				{
					$slaves_with_missing_results[] = $slave_host;
				}
			}

			// 5). Handle missing files
			if (count($slaves_with_missing_results) > 0)
			{
				$this->handle_missing_files($slaves_with_missing_results);
			}

			// 6). Aggregate the results
			if (count($results_paths) > 0)
			{
				$this->aggregate_results($results_paths);
				File::delete_directory($results_directory);
			}
		}
	}

	// Abstract methods
	/**
	 * Return an array of the hostnames of all slaves available to this master
	 *
	 * @abstract
	 * @return string[]
	 */
	abstract protected function slave_hosts();

	/**
	 * Create the instances of each of the slave jobs to be run. Each slave job instantiated and returned
	 * in the array should implement ISlave and the cardinatlity of that array should be
	 * equal to $num_slaves.
	 *
	 * This method is responsible essentially for distributing the workload.
	 *
	 * @abstract
	 * @param int $num_slaves - the number of slaves that this master has access to
	 * @return ISlave[]
	 */
	abstract protected function slave_jobs($num_slaves);

	// Virtual methods
	/**
	 * If the result of this distributed job is a file, then this method needs to be overriden to return the
	 * absolute path of that result file that will be generated by the slave. If the slave does not write
	 * to a file, then this method should return null.
	 *
	 * @return string|null - the absolute path to the file on the slave host
	 */
	protected function expected_slave_result_file_path() { return null; }

	/**
	 * Assuming that $this->expected_slave_result_file_path() returns a non-null value, and the slave machines
	 * successfully generated their result files, then this method will be called with the absolute paths to
	 * the result files scp'd from the slaves on to the master.
	 *
	 * The implementing class is reponsible for knowing what format these test result files are in, as well
	 * as how to aggregate them.
	 *
	 * @param string[] $result_file_paths - the absolute paths to the slave result files that were scp'd
	 *      from the slave servers to the current master server (paths are on local server)
	 */
	protected function aggregate_results($result_file_paths) { }

	/**
	 * Do error handling (if any) for all the slave hosts that didn't generate a result file that
	 * was expected to. Consider this method virtual, even though every non-private PHP function is
	 * virtual.
	 *
	 * @virtual
	 * @param string[] $slaves_with_missing_result_files - hostnames of slaves that were supposed to generate
	 *      a result file, but failed to do so.
	 */
	protected function handle_missing_files($slaves_with_missing_result_files)
	{
		foreach ($slaves_with_missing_result_files as $slave)
		{
			$this->witness->log_error("$slave failed to produce a junit result for " . get_called_class());
		}
	}

	// Helpers that should be private, but for testing/mocking purposes are made protected
	/**
	 * Serialize the slave job constructor arguments and scp's that instance over to the slave host.
	 * From there we SSH to the slave and pass in the class name of the slave, and construct it with
	 * the serialized parameters that were scp'd over.
	 *
	 * This function also deals with file cleanup (doesn't leave behind *.serialized files on master).
	 *
	 * @param ISlave[] $slave_job
	 * @param string $slave_host
	 * @return string - the command that was executed to launch slave job
	 */
	protected function send_job_to_slave($slave_job, $slave_host)
	{
		$unique_path_for_serialized_arguments = $this->temp_directory() . '/slave_args_' . $this->timestamp_for_this_run . '.serialized';

		// Send the serialized constructor parameters for the slave
		$serialized_file = new File($unique_path_for_serialized_arguments . '.local');
		$serialized_file->put_contents(GlobalFunctions::serialize($slave_job->get_constructor_args()));

		$result = $this->shell->command(
			"scp %s %s:%s",
			$serialized_file->path(),
			$slave_host,
			$unique_path_for_serialized_arguments
		);

		if (!$result->is_successful())
		{
			throw new \Exception("Error scp'ing to $slave_host with output: \n\n" . $result->output());
		}

		// Execute the slave job
		$host_and_script_cmd = sprintf(
			"ssh %s %s",
			$slave_host,
			__DIR__ . '/bin/run_slave_from_serialized_file'
		);

		// If there are any commands for ssh'ing into the same host running the same run_slave_... script,
		// then it's leftover from the previous run, so kill it.
		$this->kill_procs_with_command($host_and_script_cmd);

		// The below is terrible -- due to escaping issues of PHP namespaced class names (backslashes), and the way
		// the 'ps' command in linux returns strings, the executed command and the command that we will use to check
		// if the ssh connections are still alive must be different (only difference are the quotes around the
		// slave-class command line argument
		$to_execute_command = escapeshellcmd(sprintf(
			"%s --args-path %s --slave-class '%s'",
			$host_and_script_cmd,
			$unique_path_for_serialized_arguments,
			get_class($slave_job)
		));
		$ps_queryable_command = escapeshellcmd(sprintf(
			"%s --args-path %s --slave-class %s",
			$host_and_script_cmd,
			$unique_path_for_serialized_arguments,
			get_class($slave_job)
		));

		$final_cmd = sprintf(
			"( %s > %s &) && echo 0",
			$to_execute_command,
			$this->slave_log_file_path($slave_host)
		);

		$result = $this->shell->command($final_cmd);

		if (!$result->is_successful())
		{
			throw new \Exception("Error ssh'ing to $slave_host with command $final_cmd to execute slave job with output: " . implode("\n", $result->output()));
		}

		$serialized_file->delete_if_exists();

		return $ps_queryable_command;
	}

	/**
	 * This method just for all of the slaves to complete their jobs.
	 * Implemented by checking up on the executed SSH commands and waiting
	 * until they complete.
	 *
	 * This method also prints out to the console the output from the commands
	 * from the slaves.
	 *
	 * @param string[] $slave_ssh_commands
	 * @param string[] $slave_hosts
	 */
	protected function block_until_slaves_finish($slave_ssh_commands, $slave_hosts)
	{
		$tailers = array();

		foreach ($slave_hosts as $slave_host)
		{
			$tailers[] = new FileTailer($this->slave_log_file_path($slave_host));
		}

		do
		{
			// Check if slaves are still working
			$children_alive = $this->are_any_commands_running($slave_ssh_commands);

			// Output console from slaves
			for ($i = 0; $i < count($slave_hosts); $i++)
			{
				$message = $tailers[$i]->getNext();

				if (strlen($message) > 0)
				{
					$this->witness->log_information("\nOutput from slave host " . $slave_hosts[$i] . ":\n\n$message");
				}
			}

			if ($children_alive)
			{
				GlobalFunctions::sleep(5);
			}
		}
		while ($children_alive);

		// Delete file tailer files
		foreach ($tailers as $tailer)
		{
			$log_file = new File($tailer->file_path());
			$log_file->delete_if_exists();
		}
	}

	/**
	 * Returns whether any of the given list of commands are running on the current host.
	 *
	 * @param string[] $commands - set of commands to check if running
	 * @return bool
	 */
	private function are_any_commands_running($commands)
	{
		$result = $this->shell->command(
			"ps -u %s -o stat,cmd --no-headers",
			$this->shell->get_effective_user_name()
		);

		foreach ($result->output() as $ps_line)
		{
			$parts = explode(' ', $ps_line);
			$stat = $parts[0];
			$running_cmd = trim(substr($ps_line, strlen($stat)));

			foreach ($commands as $subject_command)
			{
				if (strpos($running_cmd, $subject_command) !== false && strpos($stat, 'Z') === false)
				{
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Kill processes on the current host that are running $command.
	 *
	 * @param string $command
	 */
	private function kill_procs_with_command($command)
	{
		$result = $this->shell->command(
			"ps -u %s -o pid,cmd --no-headers",
			$this->shell->get_effective_user_name()
		);

		foreach ($result->output() as $ps_line)
		{
			$parts = explode(' ', $ps_line);
			$pid = $parts[0];
			$running_cmd = trim(substr($ps_line, strlen($pid)));
			$pid = intval($pid);

			if (strpos($running_cmd, $command) !== false)
			{
				$this->witness->log_information("Killing $command from a previous distributed job run.");
				GlobalFunctions::posix_kill($pid, 9);
			}
		}
	}

	/**
	 * Copies the result file from the slave to the master. Returns true if successfully copied.
	 *
	 * @param string $slave_host - slave hostname
	 * @param string $remote_path - the absolute path in $slave_host that should have the junit result
	 * @param File $local_file_destination - where to copy the junit result to locally
	 * @param Shell $shell
	 * @return bool - success?
	 */
	protected function copy_result_from_slave($slave_host, $remote_path, $local_file_destination, $shell)
	{
		$local_file_destination->delete_if_exists();

		$cmd = $this->remote_path_is_file($slave_host, $remote_path) ? 'scp' : 'scp -r';
		$cmd .= " $slave_host:$remote_path " . $local_file_destination->path();
		return $shell->passthru($cmd)->is_successful();
	}

	/**
	* Deletes file or directory on slave
	*
	* @param string $slave_host
	* @param string $remote_path
	*/
	protected function delete_file_on_slave($slave_host, $remote_path)
	{
		$cmd = $this->remote_path_is_file($slave_host, $remote_path) ? 'rm' : 'rm -r';
		$this->shell->command("ssh $slave_host $cmd $remote_path");
	}

	/**
	 * @param string $slave_host
	 * @return string - absolute path to where the log file for a given slave should live
	 */
	private function slave_log_file_path($slave_host)
	{
		return sprintf("%s/clustered_slave_output_%s_%s", $this->temp_directory(), $this->timestamp_for_this_run, $slave_host);
	}

	/**
	 * Is $remote_path on $slave_host a file?
	 *
	 * Implementation note: an ssh connection is made with the command to exit with a specific
	 * exit code if a certain file exists on the remote server. We then return whether or not
	 * the exit code of the command was that arbitrary exit code, and if so then we deem it to
	 * be a file, and nto a directory.
	 *
	 * @param string $slave_host
	 * @param string $remote_path - absolute path on remote host
	 * @return bool
	 */
	protected function remote_path_is_file($slave_host, $remote_path)
	{
		$command_result = $this->shell->command("ssh $slave_host '[ -f $remote_path ]'");
		return $command_result->is_successful();
	}

	/**
	 * Get the absolute path to the temp directory for this host.
	 *
	 * @return string
	 */
	protected function temp_directory()
	{
		return GlobalFunctions::sys_get_temp_dir();
	}
}
