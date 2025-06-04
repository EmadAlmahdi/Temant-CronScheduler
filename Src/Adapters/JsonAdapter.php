<?php declare(strict_types=1);

namespace Temant\ScheduleManager\Adapters;

use RuntimeException;
use Temant\ScheduleManager\CronJob;
use Temant\ScheduleManager\Enums\Level;
use Throwable;

/**
 * JsonAdapter is an adapter for managing cron jobs using JSON files.
 */
final class JsonAdapter implements AdapterInterface
{
    /** @var string */
    private string $jobsFile;

    /** @var string */
    private string $logsFile;

    /** @var array<array{name: string, schedule: string, command: ?string, closure: ?string, created_at: string, updated_at: string}> */
    private array $jobsData = [];

    /** @var array<array{job_name: string, message: string, status: string, timestamp: string}> */
    private array $logsData = [];

    /**
     * JsonAdapter constructor.
     * Initializes the JSON file paths and loads existing data.
     * 
     * @param string $directory The directory where JSON files will be stored
     * @param string $jobsFilename The name of the jobs JSON file
     * @param string $logsFilename The name of the logs JSON file
     */
    public function __construct(
        string $directory,
        string $jobsFilename = 'temant_sm_jobs.json',
        string $logsFilename = 'temant_sm_logs.json'
    ) {
        // Ensure directory exists
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                throw new RuntimeException("Failed to create directory: $directory");
            }
        }

        $this->jobsFile = rtrim($directory, '/') . '/' . $jobsFilename;
        $this->logsFile = rtrim($directory, '/') . '/' . $logsFilename;

        $this->initializeFiles();
        $this->loadData();
    }

    /**
     * Initializes the JSON files if they don't exist.
     * 
     * @throws RuntimeException If files cannot be created
     */
    private function initializeFiles(): void
    {
        if (!file_exists($this->jobsFile)) {
            if (file_put_contents($this->jobsFile, '[]') === false) {
                throw new RuntimeException("Failed to create jobs file: {$this->jobsFile}");
            }
        }

        if (!file_exists($this->logsFile)) {
            if (file_put_contents($this->logsFile, '[]') === false) {
                throw new RuntimeException("Failed to create logs file: {$this->logsFile}");
            }
        }
    }

    /**
     * Loads data from JSON files into memory.
     * 
     * @throws RuntimeException If files cannot be read or decoded
     */
    private function loadData(): void
    {
        try {
            $jobsContent = file_get_contents($this->jobsFile);
            $logsContent = file_get_contents($this->logsFile);

            if ($jobsContent === false || $logsContent === false) {
                throw new RuntimeException("Failed to read JSON files");
            }

            /** @var array<array{name: string, schedule: string, command: ?string, closure: ?string, created_at: string, updated_at: string}> $jobsData */
            $jobsData = json_decode($jobsContent, true, 512, JSON_THROW_ON_ERROR);
            $this->jobsData = $jobsData;

            /** @var array<array{job_name: string, message: string, status: string, timestamp: string}> $logsData */
            $logsData = json_decode($logsContent, true, 512, JSON_THROW_ON_ERROR);
            $this->logsData = $logsData;
        } catch (Throwable $e) {
            throw new RuntimeException("Error loading JSON data: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Saves the current in-memory data to JSON files.
     * 
     * @throws RuntimeException If files cannot be written
     */
    private function persistData(): void
    {
        try {
            $jobsResult = file_put_contents(
                $this->jobsFile,
                json_encode($this->jobsData, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
            );

            $logsResult = file_put_contents(
                $this->logsFile,
                json_encode($this->logsData, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)
            );

            if ($jobsResult === false || $logsResult === false) {
                throw new RuntimeException("Failed to write to JSON files");
            }
        } catch (Throwable $e) {
            throw new RuntimeException("Error persisting JSON data: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @inheritDoc
     * 
     * @throws RuntimeException If job cannot be added
     */
    public function add(CronJob $job): void
    {
        try {
            // Remove existing job with same name if it exists
            $this->jobsData = array_values(array_filter(
                $this->jobsData,
                fn(array $j): bool => $j['name'] !== $job->name
            ));

            $this->jobsData[] = [
                'name' => $job->name,
                'schedule' => $job->schedule,
                'command' => $job->command,
                'closure' => $job->closure,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            $this->persistData();
        } catch (Throwable $e) {
            throw new RuntimeException("Error adding cron job '{$job->name}': " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @inheritDoc
     * 
     * @return array<int, CronJob>
     * @throws RuntimeException If jobs cannot be fetched
     */
    public function all(): array
    {
        try {
            return array_map(
                /** @param array{name: string, schedule: string, command: ?string, closure: ?string} $job */
                fn(array $job): CronJob => new CronJob(
                    $job['name'],
                    $job['schedule'],
                    $job['command'],
                    $job['closure']
                ),
                $this->jobsData
            );
        } catch (Throwable $e) {
            throw new RuntimeException("Error fetching all cron jobs: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @inheritDoc
     * 
     * @throws RuntimeException If job cannot be deleted
     */
    public function delete(string $name): void
    {
        try {
            $initialCount = count($this->jobsData);
            $this->jobsData = array_values(array_filter(
                $this->jobsData,
                /** @param array{name: string} $job */
                fn(array $job): bool => $job['name'] !== $name
            ));

            if (count($this->jobsData) < $initialCount) {
                $this->persistData();
            }
        } catch (Throwable $e) {
            throw new RuntimeException("Error deleting cron job '$name': " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @inheritDoc
     * 
     * @throws RuntimeException If job cannot be fetched
     */
    public function get(string $name): ?CronJob
    {
        try {
            foreach ($this->jobsData as $job) {
                if ($job['name'] === $name) {
                    return new CronJob(
                        $job['name'],
                        $job['schedule'],
                        $job['command'],
                        $job['closure']
                    );
                }
            }

            return null;
        } catch (Throwable $e) {
            throw new RuntimeException("Error fetching cron job '$name': " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @inheritDoc
     * 
     * @throws RuntimeException If job cannot be updated
     */
    public function update(CronJob $job): void
    {
        try {
            $found = false;
            foreach ($this->jobsData as &$existingJob) {
                if ($existingJob['name'] === $job->name) {
                    $existingJob['schedule'] = $job->schedule;
                    $existingJob['command'] = $job->command;
                    $existingJob['closure'] = $job->closure;
                    $existingJob['updated_at'] = date('Y-m-d H:i:s');
                    $found = true;
                    break;
                }
            }

            if ($found) {
                $this->persistData();
            } else {
                $this->add($job);
            }
        } catch (Throwable $e) {
            throw new RuntimeException("Error updating cron job '{$job->name}': " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @inheritDoc
     * 
     * @throws RuntimeException If log cannot be written
     */
    public function log(CronJob $job, string $message, Level $level = Level::SUCCESS): void
    {
        try {
            $this->logsData[] = [
                'job_name' => $job->name,
                'message' => $message,
                'status' => $level->value,
                'timestamp' => date('Y-m-d H:i:s')
            ];

            $this->persistData();
        } catch (Throwable $e) {
            throw new RuntimeException("Error logging cron job '{$job->name}': " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @inheritDoc
     * 
     * @throws RuntimeException If existence check fails
     */
    public function has(string $name): bool
    {
        try {
            foreach ($this->jobsData as $job) {
                if ($job['name'] === $name) {
                    return true;
                }
            }

            return false;
        } catch (Throwable $e) {
            throw new RuntimeException("Error checking existence of cron job '$name': " . $e->getMessage(), 0, $e);
        }
    }
}