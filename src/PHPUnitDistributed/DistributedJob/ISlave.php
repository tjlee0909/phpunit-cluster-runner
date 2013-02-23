<?php
namespace PHPUnitDistributed\DistributedJob;
/**
 * A genericized cluster runner slave. The slave does the actual work in the cluster.
 *
 * Implementation detail: do not put too much logic in the constructor of the implementing class.
 * This is because the constructor is called twice: once in the master and once in the slave. The
 * reason for this duplication is complicated, but ultimately due to the inability to serialize
 * classes that use closures--and so the next-best thing was to serialize the constructor arguments
 * and re-instantiate this object on the slave.
 */
interface ISlave
{
	/**
	 * Return the parameters passed into the constructor. All of the constructor arguments MUST MUST MUST
	 * be serializable, meaning NO closures.
	 *
	 * This method is needed in order to serialize the constructor arguments to send to the slave host
	 * and instantiate the DistributedJob_ISlave instance on the slave.
	 *
	 * @abstract
	 * @return array
	 */
	public function get_constructor_args();

	/**
	 * Do the actual work! Any input to the slave from the master should be done through the constructor
	 * in the class that implements this interface.
	 *
	 * @abstract
	 * @return mixed
	 */
	public function run();
}
