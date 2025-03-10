<?php declare(strict_types=1);

namespace Temant\ScheduleManager;

use Temant\ScheduleManager\Adapters\AdapterInterface;
use Temant\ScheduleManager\Enums\Level;
use Throwable;

/**
 * Class CronJobsManager
 * Manages a queue of cron jobs, with database persistence and execution logging.
 */
class CronJobsManager
{
    public function __construct(
        private readonly AdapterInterface $adapter
    ) {
    }

    /**
     * Executes all scheduled cron jobs in the queue.
     * 
     * This method iterates through each cron job in the queue and attempts to execute them. 
     * For each job, a log entry is made indicating the start and end of its execution. 
     * If a job fails during execution, an error is logged with the details of the failure.
     *
     * @return void
     */
    public function runJobs(): void
    {
        array_map(function (CronJob $job) {
            try {
                $this->adapter->log($job, "Starting execution of cron job '{$job->name}'...", Level::INFO);

                $job->run($this->adapter);

                $this->adapter->log($job, "Cron job '{$job->name}' executed successfully.", Level::SUCCESS);
            } catch (Throwable $th) {
                $this->adapter->log($job, "Cron job '{$job->name}' failed to execute. Error: {$th->getMessage()}", Level::ERROR);
            }
        }, $this->adapter->all());
    }

    /**
     * Adds a job to the schedule if it doesn't already exist.
     *
     * @param CronJob $job The cron job to be added.
     */
    public function addJob(CronJob $job): void
    {
        if (!$this->adapter->has($job->name)) {
            $this->adapter->add($job);
        }
    }

    /**
     * Removes a cron job from the schedule.
     *
     * @param string $name The name of the cron job to remove.
     */
    public function removeJob(string $name): void
    {
        $this->adapter->delete($name);
    }

    /**
     * Checks if a cron job exists.
     *
     * @param string $name The name of the cron job.
     * @return bool True if the job exists, false otherwise.
     */
    public function hasJob(string $name): bool
    {
        return $this->adapter->has($name);
    }

    /**
     * Retrieves all scheduled cron jobs.
     *
     * @return CronJob[] List of all cron jobs.
     */
    public function listJobs(): array
    {
        return $this->adapter->all();
    }

    /**
     * Retrieves a specific cron job by name.
     *
     * @param string $name The name of the cron job.
     * @return CronJob|null The cron job if found, null otherwise.
     */
    public function getJob(string $name): ?CronJob
    {
        return $this->adapter->get($name);
    }

    /**
     * Updates the details of a cron job.
     *
     * @param CronJob $job The updated cron job.
     */
    public function updateJob(CronJob $job): void
    {
        $this->adapter->update($job);
    }
}