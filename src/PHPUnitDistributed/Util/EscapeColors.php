<?php
namespace PHPUnitDistributed\Util;
/**
 * Color escapes for bash output
 */
class EscapeColors
{
	private static $foreground = array(
		'black' => '0;30',
		'dark_gray' => '1;30',
		'red' => '0;31',
		'bold_red' => '1;31',
		'green' => '0;32',
		'bold_green' => '1;32',
		'brown' => '0;33',
		'yellow' => '1;33',
		'blue' => '0;34',
		'bold_blue' => '1;34',
		'purple' => '0;35',
		'bold_purple' => '1;35',
		'cyan' => '0;36',
		'bold_cyan' => '1;36',
		'white' => '1;37',
		'bold_gray' => '0;37',
	);

	private static $background = array(
		'black' => '40',
		'red' => '41',
		'magenta' => '45',
		'yellow' => '43',
		'green' => '42',
		'blue' => '44',
		'cyan' => '46',
		'light_gray' => '47',
	);

	public static function should_use_color()
	{
		$info = new SystemInfo();
		return !$info->is_windows_os() && $info->use_color();
	}

	public static function underline($string)
	{
		if (!self::should_use_color()) return $string;
		return "\033[4m$string\033[0m";
	}

	/**
	 * Make string appear in color
	 */
	public static function fg_color($color, $string)
	{
		if (!self::should_use_color()) return $string;
		if (!isset(self::$foreground[$color]))
		{
			return $string;
		}

		return "\033[" . self::$foreground[$color] . "m" . $string . "\033[0m";
	}

	/**
	 * Make string appear with background color
	 */
	public static function bg_color($color, $string)
	{
		if (!self::should_use_color()) return $string;
		if (!isset(self::$background[$color]))
		{
			return $string;
		}

		return "\033[" . self::$background[$color] . 'm' . $string . "\033[0m";
	}

	/**
	 * See what they all look like
	 */
	public static function all_fg()
	{
		foreach (self::$foreground as $color => $code)
		{
			echo "$color - " . self::fg_color($color, 'Hello, world!') . PHP_EOL;
		}
	}

	/**
	 * See what they all look like
	 */
	public static function all_bg()
	{
		foreach (self::$background as $color => $code)
		{
			echo "$color - " . self::bg_color($color, 'Hello, world!') . PHP_EOL;
		}
	}

	/**
	 * Shortcut to fg_color
	 * @param string $color See possible colors with self::all_fg() methods
	 * @param array $arguments message to be colored
	 */
	public static function __callStatic($color, $arguments)
	{
		return self::fg_color($color, $arguments[0]);
	}
}
