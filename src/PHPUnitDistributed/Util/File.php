<?php
namespace PHPUnitDistributed\Util;
/**
 * Represents a file.
 */
class File
{
	/** @var Witness */
	protected $witness;
	/** @var string */
	protected $path;
	/** @var pathinfo */
	protected $pathinfo;

	/**
	 * @param string $path - the absolute path to this file
	 */
	public function __construct($path)
	{
		$this->witness = new Witness();
		$path = trim($path);

		if (!$path || empty($path))
		{
			$this->witness->log_error('$path is a required parameter and was not specified.');
			return;
		}

		$this->path = $path;
	}

	/**
	 * Returns the absolute path of this file
	 * @return string
	 */
	public function path()
	{
		return $this->path;
	}

	/**
	 * Returns the string extension, without the period, of this file
	 * @return string
	 */
	public function extension()
	{
		$pathinfo = $this->pathinfo();
		return $pathinfo['extension'];
	}

	/**
	 * Returns whether this file exists in the filesystem or not
	 * @return bool
	 */
	public function exists()
	{
		return GlobalFunctions::file_exists($this->path);
	}

	public function is_empty()
	{
		return GlobalFunctions::file_get_contents($this->path) === '';
	}

	public function delete_if_exists()
	{
		if ($this->exists())
		{
			GlobalFunctions::unlink($this->path);
		}
	}

	public function put_contents($contents)
	{
		GlobalFunctions::file_put_contents($this->path(), $contents);
	}

	/**
	 * Lazily return the pathinfo
	 * @return pathinfo
	 */
	protected function pathinfo()
	{
		if (!$this->pathinfo)
		{
			$this->pathinfo = GlobalFunctions::pathinfo($this->path());
		}
		return $this->pathinfo;
	}

	protected function ends_with($needle, $haystack)
	{
		return substr($haystack, -strlen($needle)) == $needle;
	}

	protected function begins_with($needle, $haystack)
	{
		return !strncmp($haystack, $needle, strlen($needle));
	}

	/**
	 * Checks to see if the path ends in a slash, and appends one if there is
	 * not one already.
	 *
	 * @static
	 * @param string $directory_path
	 * @return string
	 */
	public static function append_slash_if_not_exists($directory_path)
	{
		if ($directory_path && substr($directory_path, -1) != '/')
		{
			$directory_path .= '/';
		}
		return $directory_path;
	}

    /**
   	 * Recursively deletes all files and subdirectories under $dir.
   	 * Also deletes $dir as well after deleting all sub directories/files.
   	 *
   	 * @static
   	 * @param string $dir - the directory to be deleted
   	 */
   	public static function delete_directory($dir)
   	{
   		if (!GlobalFunctions::is_dir($dir)) return;

   		$witness = new Witness();
   		$iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir), \RecursiveIteratorIterator::CHILD_FIRST);

   		try
   		{
   			foreach ($iterator as $path)
   			{
   				if (substr($path, -1) === '.') continue;

   			    if ($path->isDir())
   			    {
                    GlobalFunctions::rmdir($path->__toString());
   				    $witness->log_information('Successfully removed directory ' . $path->__toString());
   			    }
   			    else
   			    {
                    GlobalFunctions::unlink($path->__toString());
                    $witness->log_information('Successfully deleted file ' . $path->__toString());
   			    }
   		    }

            GlobalFunctions::rmdir($dir);
   			$witness->log_information('Successfully removed directory ' . $dir);
   		}
   		catch (\Exception $e)
   		{
   			$witness->log_error("There was an exception while trying to recursively remove directory $dir with message: \n\n"
   					. $e->getMessage() . "\n\n" . $e->getTraceAsString());
   		}
   	}
}
