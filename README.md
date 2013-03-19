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
- The application code (PHP and PHPUnit files) must consistently be in the same directory on each of the hosts with the same permissions. The same goes for the directory that this package is installed in (same location with same permissions on all hosts).

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

