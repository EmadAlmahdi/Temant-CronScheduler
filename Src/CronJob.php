<?php declare(strict_types=1);

namespace Temant\ScheduleManager;

use Closure;
use Cron\CronExpression;
use RuntimeException;
use Temant\ScheduleManager\Adapters\AdapterInterface;
use Temant\ScheduleManager\Enums\Level; 
use Temant\Timer\Timer;
use Throwable;

use function exec;
use function is_callable;
use function Opis\Closure\serialize;
use function Opis\Closure\unserialize;

/**
 * Class CronJob
 * 
 * Represents a scheduled cron job that can either execute a command or a closure at specified intervals.
 */
class CronJob
{
    public ?string $closure = null;

    /**
     * CronJob constructor.
     * 
     * Initializes a new CronJob instance with a name, schedule, optional command, and optional closure.
     *
     * @param string $name The unique name of the cron job.
     * @param string $schedule A valid cron expression defining when the job should run.
     * @param string|null $command A shell command to execute (optional).
     * @param string|null|Closure(CronJob):void $closure A callback function to execute instead of a command (optional).
     */
    public function __construct(
        public string $name,
        public string $schedule,
        public ?string $command = null,
        Closure|string|null $closure = null,
    ) {
        $this->closure = $closure instanceof Closure
            ? serialize($closure)
            : $closure;
    }

    /**
     * Executes the cron job if it is due according to its schedule.
     * 
     * This method checks if the job is due to run based on the cron expression, and if so, 
     * either executes the associated closure or command. It also logs the execution results.
     * 
     * @param AdapterInterface $adapter The adapter used to log the results of the execution.
     * 
     * @return void
     * @throws RuntimeException if no command or closure is provided for the cron job.
     * @throws Throwable If there is an error during the execution of the closure or command.
     */
    public function run(AdapterInterface $adapter): void
    {
        if (!$this->isDue()) {
            $adapter->log($this, "Job is not due to run.", Level::INFO);
            return;
        }

        try {
            if ($this->closure) {
                $this->executeClosure($adapter, $this->closure);
            } elseif ($this->command) {
                $this->executeCommand($adapter, $this->command);
            } else {
                throw new RuntimeException("No command or closure provided for cron job '{$this->name}'.");
            }
        } catch (Throwable $th) {
            $adapter->log($this, "Cron job failed to execute. Error: {$th->getMessage()}", Level::ERROR);
        }
    }

    /**
     * Executes the closure associated with the cron job.
     * 
     * This method unserializes and executes the closure, then logs the execution time and result.
     * 
     * @param AdapterInterface $adapter The adapter used to log the execution results.
     * @param string $serilizedClosure The serialized closure string to be unserialized and executed.
     * 
     * @return void
     * @throws RuntimeException If the closure is invalid or cannot be called.
     * @throws Throwable If the closure execution fails.
     */
    private function executeClosure(AdapterInterface $adapter, string $serilizedClosure): void
    {
        $callback = unserialize($serilizedClosure);

        if (!is_callable($callback)) {
            throw new RuntimeException("Invalid closure for cron job '{$this->name}'.");
        }

        $executionTime = Timer::measure(function () use ($callback, $adapter) {
            $callback($this);
            $adapter->log($this, "Callback executed successfully.", Level::SUCCESS);
        });

        $adapter->log($this, "Callback took $executionTime seconds to execute.", Level::INFO);
    }

    /**
     * Executes the shell command associated with the cron job.
     * 
     * This method runs the command, logs the result, and logs the execution time.
     * 
     * @param AdapterInterface $adapter The adapter used to log the execution results.
     * @param string $command The shell command to be executed.
     * 
     * @return void
     * @throws Throwable If the command execution fails.
     */
    private function executeCommand(AdapterInterface $adapter, string $command): void
    {
        $executionTime = Timer::measure(function () use ($adapter, $command) {
            exec($command, $output, $code);
            $code = $code === 0 ? Level::SUCCESS : Level::ERROR;
            $message = $code === Level::SUCCESS ? "Command executed successfully." : "Command failed to execute. Error: " . implode("\n", $output);
            $adapter->log($this, $message, $code);
        });

        $adapter->log($this, "Command took $executionTime seconds to execute.", Level::INFO);
    }

    /**
     * Checks if the cron job is due to run based on its cron expression schedule.
     * 
     * This method uses the CronExpression library to evaluate the job's schedule and determine
     * if the job should be executed.
     * 
     * @return bool Returns true if the job is due to run, otherwise false.
     */
    public function isDue(): bool
    {
        return new CronExpression($this->schedule)->isDue();
    }
}