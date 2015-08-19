# SilverStripe Queued Jobs Module


[![Build Status](https://secure.travis-ci.org/silverstripe-australia/silverstripe-queuedjobs.png)](http://travis-ci.org/silverstripe-australia/silverstripe-queuedjobs)
[![Scrutinizer](https://scrutinizer-ci.com/g/silverstripe-australia/silverstripe-queuedjobs/badges/quality-score.png)](https://scrutinizer-ci.com/g/silverstripe-australia/silverstripe-queuedjobs/)


## Maintainer Contact

Marcus Nyeholt

<marcus (at) silverstripe (dot) com (dot) au>

## Requirements

* SilverStripe 3.x
* Access to create cron jobs
* https://github.com/nyeholt/silverstripe-multivaluefield

## Version info

The master branch of this module is currently aiming for SilverStripe 3.1 compatibility

* [SilverStripe 3.0 compatible version](https://github.com/silverstripe-australia/silverstripe-queuedjobs/tree/1.0)
* [SilverStripe 2.4 compatible version](https://github.com/silverstripe-australia/silverstripe-queuedjobs/tree/ss24)

## Documentation

See http://github.com/silverstripe-australia/silverstripe-queuedjobs/wiki/ for more complete
documentation

The Queued Jobs module provides a framework for SilverStripe developers to
define long running processes that should be run as background tasks.
This asynchronous processing allows users to continue using the system
while long running tasks proceed when time permits. It also lets
developers set these processes to be executed in the future.

The module comes with

* A section in the CMS for viewing a list of currently running jobs or scheduled jobs.
* An abstract skeleton class for defining your own jobs
* A task that is executed as a cronjob for collecting and executing jobs.

## Quick Usage Overview

* Install the cronjob needed to manage all the jobs within the system. It is best to have this execute as the
same user as your webserver - this prevents any problems with file permissions.

> */1 * * * * php /path/to/silverstripe/framework/cli-script.php dev/tasks/ProcessJobQueueTask

* If your code is to make use of the 'long' jobs, ie that could take days to process, also install another task
that processes this queue. Its time of execution can be left a little longer.

> */15 * * * * php /path/to/silverstripe/framework/cli-script.php dev/tasks/ProcessJobQueueTask queue=2

* From your code, add a new job for execution.

	$publish = new PublishItemsJob(21);
	singleton('QueuedJobService')->queueJob($publish);

* To schedule a job to be executed at some point in the future, pass a date through with the call to queueJob
The following will run the publish job in 1 day's time from now. 

	$publish = new PublishItemsJob(21);
	singleton('QueuedJobService')->queueJob($publish, date('Y-m-d H:i:s', time() + 86400));

## Using Doorman for running jobs

Doorman is included by default, and allows for asynchronous task processing.

This requires that you are running an a *nix based system, or within some kind of environment
emulator such as cygwin.

In order to enable this, configure the ProcessJobQueueTask to use this backend.

In your YML set the below:


```yaml
---
Name: localproject
After: '#queuedjobsettings'
---
Injector:
  QueuedJobService:
    properties: 
      queueRunner: %$DoormanRunner
```


## Using Gearman for running jobs

* Make sure gearmand is installed
* Get the gearman module from https://github.com/nyeholt/silverstripe-gearman
* Create a \_config/queuedjobs.yml file in your project with the following declaration

```
---
Name: localproject
After: '#queuedjobsettings'
---
Injector:
  QueueHandler: 
    class: GearmanQueueHandler
```

* Run the gearman worker using `php gearman/gearman_runner.php` in your SS root dir

This will cause all queuedjobs to trigger immediate via a gearman worker (code/workers/JobWorker.php)
EXCEPT those with a StartAfter date set, for which you will STILL need the cron settings from above

## Using QueuedJob::IMMEDIATE jobs

Queued jobs can be executed immediately (instead of being limited by cron's 1 minute interval) by using
a file based notification system. This relies on something like inotifywait to monitor a folder (by
default this is SILVERSTRIPE_CACHE_DIR/queuedjobs) and triggering the ProcessJobQueueTask as above
but passing job=$filename as the argument. An example script is in queuedjobs/scripts that will run
inotifywait and then call the ProcessJobQueueTask when a new job is ready to run. 

Note - if you do NOT have this running, make sure to set `QueuedJobService::$use_shutdown_function = true;`
so that immediate mode jobs don't stall. By setting this to true, immediate jobs will be executed after
the request finishes as the php script ends. 


## Troubleshooting

To make sure your job works, you can first try to execute the job directly outside the framework of the
queues - this can be done by manually calling the *setup()* and *process()* methods. If it works fine
under these circumstances, try having *getJobType()* return *QueuedJob::IMMEDIATE* to have execution
work immediately, without being persisted or executed via cron. If this works, next make sure your
cronjob is configured and executing correctly. 

If defining your own job classes, be aware that when the job is started on the queue, the job class
is constructed _without_ parameters being passed; this means if you accept constructor args, you
_must_ detect whether they're present or not before using them. See [this issue](https://github.com/silverstripe-australia/silverstripe-queuedjobs/issues/35) 
and [this wiki page](https://github.com/silverstripe-australia/silverstripe-queuedjobs/wiki/Defining-queued-jobs) for 
more information

Ensure that notifications are configured so that you can get updates or stalled or broken jobs. You can 
set the notification email address in your config as below:


	:::yaml
	Email:
	  queued_job_admin_email: support@mycompany.com

## Performance configuration

By default this task will run until either 128mb or the limit specified by php_ini('memory_limit') is reached.

You can adjust this with the below config change


	:::yaml
	# Force memory limit to 256 megabytes
	QueuedJobsService:
	  # Accepts b, k, m, or b suffixes
	  memory_limit: 256m


You can also enforce a time limit for each queue, after which the task will attempt a restart to release all
resources. By default this is disabled, so you must specify this in your project as below:


	:::yaml
	# Force limit to 10 minutes
	QueuedJobsService:
	  time_limit: 600


## Indexes

ALTER TABLE `QueuedJobDescriptor` ADD INDEX ( `JobStatus` , `JobType` ) 

## Contributing

### Translations

Translations of the natural language strings are managed through a third party translation interface, transifex.com. Newly added strings will be periodically uploaded there for translation, and any new translations will be merged back to the project source code.

Please use [https://www.transifex.com/projects/p/silverstripe-queuedjobs](https://www.transifex.com/projects/p/silverstripe-queuedjobs) to contribute translations, rather than sending pull requests with YAML files.
