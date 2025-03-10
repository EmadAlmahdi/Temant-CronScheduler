<?php declare(strict_types=1);

namespace Temant\ScheduleManager\Adapters;

use Temant\ScheduleManager\CronJob;

/**
 * InMemoryAdapter is an adapter that manages cron jobs in memory.
 */
class InMemoryAdapter
{
    /**
     * InMemoryAdapter constructor.
     * 
     * @param CronJob[] $jobs Initial array of cron jobs.
     */
    public function __construct(
        private array $jobs = []
    ) {
    }

    /**
     * Adds a new cron job to the collection.
     *
     * @param CronJob $job The cron job to add.
     * @return void
     */
    public function add(CronJob $job): void
    {
        $this->jobs[] = $job;
    }

    /**
     * Retrieves a cron job by its name.
     *
     * @param string $name The name of the cron job.
     * @return CronJob|null The found cron job, or null if not found.
     */
    public function get(string $name): ?CronJob
    {
        return array_find($this->jobs, fn(CronJob $job): bool => $job->name === $name);
    }

    /**
     * Deletes a cron job by its name.
     *
     * @param string $name The name of the cron job to delete.
     * @return void
     */
    public function delete(string $name): void
    {
        $this->jobs = array_filter($this->jobs, fn(CronJob $job) => $job->name !== $name);
    }

    /**
     * Retrieves all cron jobs.
     *
     * @return CronJob[] The array of cron jobs.
     */
    public function all(): array
    {
        return $this->jobs;
    }
}