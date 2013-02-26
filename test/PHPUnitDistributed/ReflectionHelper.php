<?php
namespace PHPUnitDistributed;

/**
 * Assist with PHP Reflection
 * Cache reflection information for performance
 */
class ReflectionHelper
{
	private static $classes = array();

	public static function get_class($class_name)
	{
		if (array_key_exists($class_name, self::$classes))
		{
			return self::$classes[$class_name];
		}

		// Let this throw an error if the class doesn't exist
		$reflection = new \ReflectionClass($class_name);
		self::$classes[$class_name] = $reflection;

		return $reflection;
	}

	public static function get_method($class_name, $method_name)
	{
		$class = self::get_class($class_name);
		$method = $class->getMethod($method_name);
		$method->setAccessible(true);

		return $method;
	}

	public static function get_property($class_name, $prop_name)
	{
		$class = self::get_class($class_name);
		$prop = $class->getProperty($prop_name);
		$prop->setAccessible(true);

		return $prop;
	}

	public static function set_static_property($class, $prop_name, $value)
	{
		$property = self::get_property($class, $prop_name);
		$property->setValue(null, $value);
	}

	public static function set_property($object, $prop_name, $value)
	{
		$class = get_class($object);
		$property = self::get_property($class, $prop_name);
		$property->setValue($object, $value);
	}

	public static function invoke_method_on_object($object, $method_name, $args = null)
	{
		$method = ReflectionHelper::get_method(get_class($object), $method_name);
		if ($args)
		{
			return $method->invokeArgs($object, $args);
		}
		return $method->invoke($object);
	}
}

