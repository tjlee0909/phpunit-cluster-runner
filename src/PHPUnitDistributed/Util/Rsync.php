<?php
namespace PHPUnitDistributed\Util;

class Rsync
{
	/** @var Shell */
	private $shell;
	/** @var string */
	private $source;
	/** @var string */
	private $destination;
	/** @var string[] */
	private $exclude_list;
	/** @var string */
	private $options;

	/**
	 * @param Shell $shell
	 * @param string $source - $source and $destination may incldue user and host-qualified absolute paths (ie: testuser@masterhost.net:/path/to/directory)
	 * @param string $destination
	 * @param string[] $exclude_list
	 * @param string|null  $options
	 */
	public function __construct($shell, $source, $destination, $exclude_list = array(), $options = null)
	{
		$this->shell = $shell;
		$this->source = $source;
		$this->destination = $destination;
		$this->exclude_list = $exclude_list;
		$this->options = trim($options);
	}

	/**
	 * Executes the rsync command
	 *
	 * @return bool - did the rsync complete successfully?
	 */
	public function exec()
	{
		$options_string = $this->options ? ('-' . $this->options) : '';

		$exclude_string = '';

		if ($this->exclude_list && count($this->exclude_list) > 0)
		{
			foreach ($this->exclude_list as $exclude)
			{
				$exclude_string.= " --exclude '$exclude' ";
			}
		}

		$result = $this->shell->command(sprintf(
			"rsync %s --delete %s %s %s",
			$options_string,
			$exclude_string,
			$this->source,
			$this->destination
		));

		return $result->is_successful();
	}
}
