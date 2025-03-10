<?php declare(strict_types=1);

namespace Temant\ScheduleManager\Adapters;

use Temant\ScheduleManager\CronJob;
use Temant\ScheduleManager\Enums\Level;
use Throwable;

/**
 * Interface for handling cron job storage operations.
 *
 * This interface defines the contract for interacting with a storage
 * system for cron jobs. Implementations of this interface will be responsible
 * for adding, retrieving, deleting, and managing cron jobs.
 */
interface AdapterInterface
{
    /**
     * Checks if a cron job exists in the storage.
     *
     * This method determines whether a cron job with the given name is present in the storage.
     *
     * @param string $name The name of the cron job to check.
     * @return bool True if the job exists, false otherwise.
     */
    public function has(string $name): bool;

    /**
     * Retrieves a cron job by its unique name.
     *
     * This method allows you to retrieve a specific cron job from the storage system
     * using its name. If the cron job does not exist, it returns `null`.
     *
     * @param string $name The name of the cron job to retrieve.
     * @return CronJob|null The cron job associated with the given name, or `null` if no such job exists.
     */
    public function get(string $name): ?CronJob;

    /**
     * Updates an existing cron job in the storage.
     *
     * This method updates the details of a cron job, such as its schedule, command, or closure.
     *
     * @param CronJob $job The cron job object with updated details.
     * @return void
     */
    public function update(CronJob $job): void;

    /**
     * Adds a new cron job to the storage.
     *
     * This method is responsible for saving a cron job in the underlying storage system.
     * It will typically be called when creating a new cron job to ensure it is persisted.
     *
     * @param CronJob $job The cron job object to add to the storage.
     * @return void
     */
    public function add(CronJob $job): void;

    /**
     * Deletes a cron job by its name.
     *
     * This method removes the specified cron job from the storage system. It is typically
     * called when the cron job is no longer needed or has been completed successfully.
     *
     * @param string $name The name of the cron job to delete from storage.
     * @return void
     */
    public function delete(string $name): void;

    /**
     * Retrieves all stored cron jobs.
     *
     * This method returns an array of all cron jobs currently stored in the system.
     * This could be useful for listing all available cron jobs or for bulk operations.
     *
     * @return CronJob[] An array of all cron jobs stored in the system.
     */
    public function all(): array;

    /**
     * Logs a success event or general log for a specific cron job.
     *
     * This method is responsible for logging general information or success events for a
     * cron job, such as successful execution or status updates.
     *
     * @param CronJob $job The cron job to log the event for.
     * @param string $message The message to log for the event.
     * @param Level $level The log level to use for the event.
     * @return void
     */
    public function log(CronJob $job, string $message, Level $level = Level::SUCCESS): void;
}