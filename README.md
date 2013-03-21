What is it? What problem does it solve?
======================================

PHPUnitDistributed is a package built on top of PHPUnit that distributes your PHPUnit tests to be run across several servers and aggregates the results to get you PHPUnit test results in JUnit format in a timely manner.

Many development environments face the problem of long-running PHPUnit test suites that can take several hours to complete; this can quickly become a bottleneck in the software development cycle. One way to reduce the test-running time by a multiplicative factor is by spreading the load across multiple machines, which this package accomplishes.

The class interface of PHPUnitDistributed should look familiar if you have used phpunit through the command line before. If you haven't worked with phpunit or JUnit-format result files before, then it is highly recommended that you familiarize yourself with those tools first as the rest of the documentation assumes such knowledge.

System requirements and prerequisites
======================================

In order to use the PHPUnitDistributed package, you will need at least two networked linux hosts, each with the following:

- A linux user that exists on all of the hosts and has read/write/execute access to both this package's directory and the directory of the PHP and PHPUnit files
- Has ssh key pairs setup between the master host and each slave host.   
- Has PHP 5.3+ installed.   
- Has the PHPUnit PEAR package installed.  
- The source code for this package must consistently be in the same directory on each of the hosts with the same permissions.

Quick start guide
======================================
This section will go through an example of the fastest way to get a sanity-test of running phpunit distributed across multiple hosts. If you haven't verified that your network has met the system requirements and completed the pre-requisite tasks from section 2), please make sure you have, as you won't be able to get this package to work otherwise.

For the purpose of this example, lets assume that the environment will be setup as such:

- There a three hosts in this network: master_host.your-network.net, slave_host1.your-network.net, and slave_host2.your-network.net.
- The user who will be executing the PHPUnitDistributed jobs will be test user. And this user exists on all three of the hosts above.
- Each host has the PHPUnitDistributed package installed in /home/testuser/PHPUnitDistributed.
- The application code lives in /home/testuser/my_application, and the directory structure inside is layed out as such:

```
/home/testuser/my_application/
      src/ [contains application code]
           user.php
           server.php
      test/ [contains phpunit test files]
           user_Test.php
           server_Test.php
           bootstrap.php [this is your PHPUnit bootstrap file]
```

Now, lets say that you want to run this test suite (which, in this example only contains two test files) using PHPUnitDistributed across your cluster. Although you will likely eventually be embedding this into your application, the quickest way to get something working is to create a bash script such as the following:

```php
#!/usr/bin/php
<?php
include_once dirname(dirname(__DIR__)) . '/src/PHPUnitDistributed/phpunitdistributed_common.php';

use PHPUnitDistributed\PHPUnit\Configuration;
use PHPUnitDistributed\PHPUnit\RunDistributedMaster;

$app_dir = '/home/testuser/my_application/';
$junit_result_path = '/home/testuser/junit_results.xml';
$test_directory = $app_dir . 'test/';
$bootstrap_path = $app_dir . 'test/bootstrap.php';
$slaves = array('slave_host1.your-network.net', 'slave_host2.your-network.net');

$config = new Configuration(
    $app_dir, 
    $junit_result_path , 
    array(), 
    array($test_directory),
    $bootstrap_path
);

$runner = new RunDistributedMaster($config, $slaves);
$runner->run();
```

Make sure to chmod +x the file, and execute it as testuser on master_host.your-network.net. The console should be outputting the progress from each of the machines, at the end of which /home/testuser/junt_results.xml should be generated.

Any error you may get during this quick start setup is most likely due to not completing all steps from "System requirements and prerequisites". A very similar script sample script also exists in [package_directory]/example/bin/run_sample_implementation.

Usage and API
======================================
There are two primary classes that you will be interacting with in order to use this package: PHPUnitDistributed\PHPUnit\RunDistributedMaster and PHPUnitDistributed\PHPUnit\Configuration.

**PHPUnitDistributed\PHPUnit\Configuration**

The Configuration class holds everything that you can configure phpunit-wise for the test runner. The parameters that you pass into its constructor pretty much directly get placed in the phpunit xml configuration file, which gets generated upon execution and passed in as an argument to the phpunit invocation. Below is a table of the constructor arguments for this class.

*Constructor arguments*
<table>
  <tr>
    <th>Name</th><th>Type</th><th>Description</th>
  </tr>
  <tr>
    <td>app_directory</td><td>string</td><td>The absolute path of the top-level project directory (ie: /box/www/current/) to execute phpunit in.</td>
  </tr>
  <tr>
    <td>junit_result_output_path</td><td>string</td><td>The absolute path to junit result xml file. If the file specified in path already exists, it will be overwritten when run.</td>
  </tr>
  <tr>
    <td>test_files</td><td>string[]</td><td>An array of absolute paths to the PHPUnit test files in this project. This parameter can be null/empty if and only if test_directories is specified; that is to say, one of test_files or test_directories is required.</td>
  </tr>
  <tr>
    <td>test_directories</td><td>string[]</td><td>An array of absolute paths to the directory containing PHPUnit test files in this project. If this parameter is specified, the Configuration class will recursively look for php files ending in _Test.php (case sensitive). This parameter can be null/empty as long as test_files is specified.</td>
  </tr>
  <tr>
    <td>bootstrap_file (optional, default: null)</td><td>string</td><td>The absolute path to the phpunit bootstrap file if you would like to specify one. The bootstrap file is a php file that you create that gets executed before the tests. Defaults to null if not specified.</td>
  </tr>
  <tr>
    <td>verbose (optional, default: false)</td><td>bool</td><td>Should the output of the phpunit execution be verbose? Defaults to false if not specified.</td>
  </tr>
</table>

All of the parameters above should look familiar if you have worked with PHPUnit before. If not, it's suggested that you read the documentation on the phpunit command line test runner (http://www.phpunit.de/manual/current/en/textui.html) and the phpunit xml configuration file (http://www.phpunit.de/manual/current/en/appendixes.configuration.html).

You should never have to do anything with an instance of the Configuration class except to pass it as an argument to the RunDistributedMaster constructor.

**PHPUnitDistributed\PHPUnit\RunDistributedMaster**

The RunDistributedMaster class is the top-level object to kick off the distributed phpunit job. As shown in the quick start guide, it is face of the PHPUnitDistributed package. The class has the public method run(), which coordinates the distribution of tests to slaves, the execution of phpunit on each of the slaves, and the aggregation of the results back onto the master server. At the end of the run() call, you can expect to see the junit xml file in the path you specified in the Configuration instance.

The configuration parameters of the class are delineated by the constructor arguments, documented below.

*Constructor arguments*
<table>
  <tr>
    <th>Name</th><th>Type</th><th>Description</th>
  </tr>
  <tr>
    <td>config</td><td>PHPUnitDistributed \ PHPUnit \ Configuration</td><td>All of the relevant PHPUnit configuration information should be stored here.</td>
  </tr>
  <tr>
    <td>slave_hosts</td><td>string[]</td><td>The array of hostnames of the servers/slaves that will be distributed the phpunit tests.</td>
  </tr>
  <tr>
    <td>test_division_strategy(optional, default: TestCount instance)</td><td>PHPUnitDistributed \ TestDivisionStrategy \ IStrategy</td><td>The strategy to divide PHPUnit tests across multiple servers. Caller can pass in any implementation of IStrategy, but if none is specified, the default PHPUnitDistributed\TestDivisionStrategy\TestCount implementation is used.</td>
  </tr>
  <tr>
    <td>rsync_exclude (optional, default: null)</td><td>string[]</td><td>Array of application-directory relative regex items that should not be rsynced from the master to the slaves. This argument will be forwarded as --exclude arguments to the linux rsync command. For example, if you specified '/my/application/' for $app_directory in your Configuration instance, and you do not want to rsync '/my/application/conf/machine_configuration.xml' on your slave hosts, then you would pass in array('conf/machine_configuration.xml') for this parameter.</td>
  </tr>
  <tr>
    <td>run_serially (optional, default: false)</td><td>bool</td><td>If set to true, each PHPUnit test file will be run in its own phpunit invocation.</td>
  </tr>
</table>

The first two parameters, config and slave_hosts, should be straight forward to understand. It should be noted that you can specify the master as a slave as well.

The test_division_strategy parameter refers to the PHPUnitDistributed\TestDivisionStrategy\IStrategy interface, which has a single interface method, divide_tests($num_slaves, $test_files), which is responsible for dividing the $test_files into $num_slaves groups of tests (to distributed to each slave). The default implementation that comes implemented in the PHPUnitDistributed package is PHPUnitDistributed\TestDivisionStrategy\TestCount. The TestCount strategy implementation searches each of the test files for number of test methods and distributes the test files across the slaves by as equal number of tests as possible. Note: you can implement your own test division strategy that does test division more intelligently (for example, at Box, we have a test division strategy that divides tests by estimated execution time based on historical data for those tests).

The rsync_exclude parameter may be harder to understand because it exposes an implementation detail. Before the RunDistributedMaster instance distributes the tests to the slaves, it transfers all of the code in the application directory (specified in the Configuration constructor as app_directory) from the master host to each of the slave hosts using rsync. It is often the case that there are some machine-specific configuration files, or possibly .git directories that should not be synced from master to slave; the rsync_exclude parameter is here to specify such files that should not be rsynced.

The run_serially parameter refers to a feature not present in the phpunit implementation. If you would like the default phpunit implementation of test invocation, don't specify anything for this argument, or set it to false (default value). If set to true, RunDistributedMaster will execute each test file in its own phpunit invocation. A possible use case for setting this parameter to true would be to verify that the tests don't have dependencies on global/persisted state from other tests. It is an auxiliary feature, and can be ignored by most users.

How does it work (implementation overview)?
======================================
Coming soon (might come faster if people request it)!

Email tj at box.com.
