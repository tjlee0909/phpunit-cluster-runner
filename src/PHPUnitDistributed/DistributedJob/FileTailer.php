<?php
namespace PHPUnitDistributed\DistributedJob;

class FileTailer
{
	private $nchar, $file;

	public function __construct($file)
	{
		$this->file = $file;
		$this->nchar=0;
	}

	public function getNext()
	{
		$new_str = '';
		if (file_exists($this->file))
		{
			$fh = fopen($this->file, 'r');
			fseek($fh, $this->nchar);
			while(TRUE)
			{
				$s = fread($fh, 1);
				if ($s === '')
				{
					break;
				}
				else
				{
					$new_str.= $s;

				}
			}
			fclose($fh);
			$this->nchar+=strlen($new_str);
		}
		return $new_str;
	}

	public function file_path()
	{
		return $this->file;
	}
}
