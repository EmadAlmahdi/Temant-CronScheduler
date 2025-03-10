<?php declare(strict_types=1);

namespace Temant\ScheduleManager\Adapters;

use PDO;
use RuntimeException;
use Temant\ScheduleManager\CronJob;
use Temant\ScheduleManager\Enums\Level;
use Throwable;

/**
 * SqlLiteAdapter is an adapter for managing cron jobs using SQLite.
 */
final class SqlLiteAdapter implements AdapterInterface
{
    private readonly PDO $db;

    private readonly string $jobsTable;

    private readonly string $logsTable;

    /**
     * SqlLiteAdapter constructor.
     * Initializes the SQLite database connection and sets up the schema.
     * 
     * @param string $path The path to the SQLite database file. 
     * @param string $tablePrefix The prefix to use for the database tables.
     */
    public function __construct(
        string $path,
        string $tablePrefix = "temant_sm_"
    ) {

        $this->jobsTable = "{$tablePrefix}jobs";
        $this->logsTable = "{$tablePrefix}logs";

        try {
            $this->db = new PDO("sqlite:$path");
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->initializeDatabase();
        } catch (Throwable $e) {
            throw new RuntimeException("Failed to initialize SQLite connection: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Initializes the SQLite database schema.
     * Creates tables if they don't exist.
     * 
     * @throws RuntimeException If there is a problem creating the tables.
     */
    private function initializeDatabase(): void
    {
        try {
            $this->db->exec("CREATE TABLE IF NOT EXISTS $this->jobsTable (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                schedule TEXT NOT NULL,
                command TEXT,
                closure TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(name) 
            )");

            $this->db->exec("CREATE TABLE IF NOT EXISTS $this->logsTable (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                job_name TEXT NOT NULL,
                message TEXT NOT NULL,
                status TEXT NOT NULL,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
        } catch (Throwable $e) {
            throw new RuntimeException("Error initializing the database schema: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function add(CronJob $job): void
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT OR REPLACE INTO $this->jobsTable (name, schedule, command, closure, updated_at) VALUES (:name, :schedule, :command, :closure, CURRENT_TIMESTAMP)"
            );

            $stmt->bindValue(':name', $job->name, PDO::PARAM_STR);
            $stmt->bindValue(':schedule', $job->schedule, PDO::PARAM_STR);
            $stmt->bindValue(':command', $job->command, PDO::PARAM_STR);
            $stmt->bindValue(':closure', $job->closure, PDO::PARAM_STR);
            $stmt->execute();
        } catch (Throwable $e) {
            throw new RuntimeException("Error adding cron job '{$job->name}': " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function all(): array
    {
        try {
            $stmt = $this->db->query("SELECT * FROM $this->jobsTable");

            if (!$stmt) {
                throw new RuntimeException("Error Processing Request", 1);
            }

            /**
             * @var array<array{
             * id:int,
             * name:string,
             * schedule:string,
             * command: ?string,
             * closure: ?string,
             * created_at:string,
             * updated_at:string
             * }> $jobs
             */
            $jobs = $stmt->fetchAll();

            return array_map(fn(array $job): CronJob => new CronJob(
                $job['name'],
                $job['schedule'],
                $job['command'],
                $job['closure']
            ), $jobs);
        } catch (Throwable $e) {
            throw new RuntimeException("Error fetching all cron jobs: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function delete(string $name): void
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM $this->jobsTable WHERE name = :name");
            $stmt->bindValue(':name', $name, PDO::PARAM_STR);
            $stmt->execute();
        } catch (Throwable $e) {
            throw new RuntimeException("Error deleting cron job '$name': " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function get(string $name): ?CronJob
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM $this->jobsTable WHERE name = :name");
            $stmt->bindValue(':name', $name, PDO::PARAM_STR);
            $stmt->execute();

            /**
             * @var ?CronJob $job
             */
            $job = $stmt->fetch(PDO::FETCH_OBJ);

            return $job ? new CronJob($job->name, $job->schedule, $job->command, $job->closure) : null;
        } catch (Throwable $e) {
            throw new RuntimeException("Error fetching cron job '$name': " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function update(CronJob $job): void
    {
        try {
            $stmt = $this->db->prepare("UPDATE $this->jobsTable 
                SET schedule = :schedule, command = :command, closure = :closure, updated_at = CURRENT_TIMESTAMP 
                WHERE name = :name");
            $stmt->bindValue(':name', $job->name, PDO::PARAM_STR);
            $stmt->bindValue(':schedule', $job->schedule, PDO::PARAM_STR);
            $stmt->bindValue(':command', $job->command, PDO::PARAM_STR);
            $stmt->bindValue(':closure', $job->closure, PDO::PARAM_STR);
            $stmt->execute();
        } catch (Throwable $e) {
            throw new RuntimeException("Error updating cron job '{$job->name}': " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function log(CronJob $job, string $message, Level $level = Level::SUCCESS): void
    {
        try {
            $stmt = $this->db->prepare("INSERT INTO $this->logsTable (job_name, message, status) VALUES (:job_name, :message, :status)");
            $stmt->bindValue(':job_name', $job->name, PDO::PARAM_STR);
            $stmt->bindValue(':message', $message, PDO::PARAM_STR);
            $stmt->bindValue(':status', $level->value, PDO::PARAM_STR);
            $stmt->execute();
        } catch (Throwable $e) {
            throw new RuntimeException("Error logging cron job '{$job->name}': " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function has(string $name): bool
    {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM $this->jobsTable WHERE name = :name");
            $stmt->bindValue(':name', $name, PDO::PARAM_STR);
            $stmt->execute();

            return (bool) $stmt->fetchColumn();
        } catch (Throwable $e) {
            throw new RuntimeException("Error checking existence of cron job '$name': " . $e->getMessage(), 0, $e);
        }
    }
}