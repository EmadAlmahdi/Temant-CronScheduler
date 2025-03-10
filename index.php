<?php declare(strict_types=1);

use Temant\ScheduleManager\Adapters\SqlLiteAdapter;
use Temant\ScheduleManager\CronJobsManager;
use Temant\ScheduleManager\CronJob;

require __DIR__ . '/vendor/autoload.php';

$manager = new CronJobsManager(new SqlLiteAdapter(__DIR__ . "/storage.db"));

$manager->removeJob("Log Time Command 1");

// Add a job that writes to a log file using a shell command
$manager->addJob(new CronJob(
    "Log Time Command",
    "* * * * *", // Runs every minute
    "echo Command >> E:\laragon\laragon\www\Temant-CronScheduler\cron_output.log"
));

// Add a job that writes to a log file using a callback function
$manager->addJob(new CronJob(
    "Log Time Callback",
    "* * * * *", // Runs every minute
    null,
    function (CronJob $cronJob) {
        file_put_contents(__DIR__ . "/cron_output.log", "Callback: {$cronJob->name}" . date('[Y-m-d H:i:s]') . "\n", FILE_APPEND);
    }
));

// dd($manager->getJob("Log Time Command")->getSchedule()->getNextRunDate( ));

// Execute scheduled jobs
$manager->runJobs();