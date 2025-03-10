<?php declare(strict_types=1);

namespace Temant\ScheduleManager\Parsers;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use InvalidArgumentException;
use RuntimeException;
use Stringable;

/**
 * Parses and evaluates CRON expressions, providing functionality to determine
 * if a CRON expression is due to run, as well as retrieving the next and 
 * previous run dates based on the current time or a specified time.
 * 
 * CRON expression parts correspond to:
 * - Minute [0-59]
 * - Hour [0-23]
 * - Day of month [1-31]
 * - Month [1-12|JAN-DEC]
 * - Day of week [1-7|MON-SUN]
 * - Optional year
 *
 * For further details on CRON syntax, refer to: 
 * @link http://en.wikipedia.org/wiki/Cron
 */
class CronExpression implements Stringable
{
    public const int MINUTE = 0;
    public const int HOUR = 1;
    public const int DAY = 2;
    public const int MONTH = 3;
    public const int WEEKDAY = 4;
    public const int YEAR = 5;

    /**
     * @var string[] The parts of the CRON expression
     */
    private array $cronParts;

    /**
     * @var int Maximum iterations allowed when searching for next run date
     */
    private $maxIterationCount = 1000;

    /**
     * @var int[] The order in which to evaluate the CRON parts
     */
    private static array $order = [self::YEAR, self::MONTH, self::DAY, self::WEEKDAY, self::HOUR, self::MINUTE];

    /**
     * @var string[] Mappings of special CRON expressions to their respective values
     */
    private array $mappings = [
        '@yearly' => '0 0 1 1 *',
        '@annually' => '0 0 1 1 *',
        '@monthly' => '0 0 1 * *',
        '@weekly' => '0 0 * * 0',
        '@daily' => '0 0 * * *',
        '@hourly' => '0 * * * *'
    ];

    /**
     * Validates if the provided CRON expression is well-formed.
     *
     * @param string $expression The CRON expression to validate
     *
     * @return bool True if the expression is valid, false otherwise
     */
    public static function isValidExpression(string $expression): bool
    {
        try {
            new self($expression);
        } catch (InvalidArgumentException $e) {
            return false;
        }

        return true;
    }

    /**
     * @var FieldInterface[] Cache of instantiated fields
     */
    private array $fields;

    /**
     * Constructor that initializes the CRON expression object.
     *
     * @param string $expression The CRON expression to parse and evaluate 
     */
    public function __construct(string $expression)
    {
        if (isset($this->mappings[$expression])) {
            $expression = $this->mappings[$expression];
        }

        $this->setExpression($expression);
    }

    /**
     * Sets or updates the CRON expression to a new value.
     *
     * @param string $value The new CRON expression
     * @return self The updated CronExpression instance
     * @throws InvalidArgumentException If the provided expression is invalid
     */
    public function setExpression(string $value): self
    {
        $this->cronParts = preg_split('/\s/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($this->cronParts) < 5) {
            throw new InvalidArgumentException("$value is not a valid CRON expression");
        }

        foreach ($this->cronParts as $position => $part) {
            $this->setPart($position, $part);
        }

        return $this;
    }

    /**
     * Sets a specific part of the CRON expression.
     *
     * @param int $position The position of the CRON part (minute, hour, etc.)
     * @param string $value The value to set for the CRON part
     * @return self The updated CronExpression instance
     * @throws InvalidArgumentException If the part value is invalid
     */
    public function setPart(int $position, string $value): self
    {
        if (!isset($this->fields[$position])) {
            $this->fields[$position] = match ($position) {
                self::MINUTE => new MinutesField,
                self::HOUR => new HoursField,
                self::DAY => new DayOfMonthField,
                self::MONTH => new MonthField,
                self::WEEKDAY => new DayOfWeekField,
                default => throw new InvalidArgumentException(($position + 1) . ' is not a valid position'),
            };
        }

        if (!$this->fields[$position]->validate($value)) {
            throw new InvalidArgumentException("Invalid CRON field value $value at position $position");
        }

        $this->cronParts[$position] = $value;

        return $this;
    }

    /**
     * Sets the maximum iteration count when searching for next run dates.
     *
     * @param int $maxIterationCount The maximum number of iterations
     * @return self The updated CronExpression instance
     */
    public function setMaxIterationCount(int $maxIterationCount): self
    {
        $this->maxIterationCount = $maxIterationCount;

        return $this;
    }

    /**
     * Retrieves the next run date based on a cron expression, relative to the current or a specified date.
     *
     * @param string|DateTimeInterface|null $currentTime The date/time to base the calculation on (defaults to 'now').
     * @param int $nth The number of matches to skip before returning a run date (defaults to 0).
     *             - 0 returns the current date if it matches the cron expression.
     *             - 1 skips the first match and returns the second, and so on.
     * @param bool $allowCurrentDate Whether to include the current date if it matches the cron expression (defaults to false).
     * @param null|string $timeZone The timezone to use for the calculation (defaults to system timezone).
     *
     * @return DateTimeInterface The next matching run date.
     * @throws RuntimeException If there are too many iterations or no valid result is found.
     */
    public function getNextRunDate(string|DateTimeInterface|null $currentTime = 'now', int $nth = 0, bool $allowCurrentDate = false, ?string $timeZone = null): DateTimeInterface
    {
        return $this->getRunDate($currentTime, $nth, false, $allowCurrentDate, $timeZone);
    }

    /**
     * Retrieves the previous run date based on a cron expression, relative to the current or a specified date.
     *
     * @param string|DateTimeInterface|null $currentTime The date/time to base the calculation on (defaults to 'now').
     * @param int $nth The number of matches to skip before returning a run date (defaults to 0).
     *             - 0 returns the current date if it matches the cron expression.
     *             - 1 skips the first match and returns the second, and so on.
     * @param bool $allowCurrentDate Whether to include the current date if it matches the cron expression (defaults to false).
     * @param null|string $timeZone The timezone to use for the calculation (defaults to system timezone).
     *
     * @return DateTimeInterface The previous matching run date.
     * @throws RuntimeException If there are too many iterations or no valid result is found.
     */
    public function getPreviousRunDate(string|DateTimeInterface|null $currentTime = 'now', int $nth = 0, bool $allowCurrentDate = false, ?string $timeZone = null): DateTimeInterface
    {
        return $this->getRunDate($currentTime, $nth, true, $allowCurrentDate, $timeZone);
    }

    /**
     * Retrieves multiple run dates starting from a given date or time.
     *
     * @param int $total The total number of run dates to retrieve
     * @param string|DateTimeInterface|null $currentTime The base date/time for calculation (default is 'now')
     * @param bool $invert Whether to get previous run dates instead of next (default is false)
     * @param bool $allowCurrentDate Whether to include the current date if it matches (default is false)
     * @param string|null $timeZone The time zone for the calculation (default is system time zone)
     *
     * @return DateTimeInterface[] An array of DateTimeInterface instances representing the run dates
     */
    public function getMultipleRunDates(int $total, string|DateTimeInterface|null $currentTime = 'now', bool $invert = false, bool $allowCurrentDate = false, ?string $timeZone = null): array
    {
        $matches = [];

        for ($i = 0; $i < $total; $i++) {
            try {
                $matches[] = $this->getRunDate($currentTime, $i, $invert, $allowCurrentDate, $timeZone);
            } catch (RuntimeException $e) {
                break;
            }
        }

        return $matches;
    }

    /**
     * Retrieves the CRON expression or a specific part of it.
     *
     * @param int|null $part The specific part of the CRON expression to retrieve, or NULL for the full expression
     * @return string|null The CRON expression or part of it, or null if the part was not found
     */
    public function getExpression(?int $part = null): ?string
    {
        if (null === $part) {
            return implode(' ', $this->cronParts);
        } elseif (array_key_exists($part, $this->cronParts)) {
            return $this->cronParts[$part];
        }

        return null;
    }

    /**
     * Checks if the CRON expression is due to run based on the current time or a specified date.
     *
     * @param string|DateTimeInterface $currentTime The time to compare against (defaults to 'now')
     * @param string|null $timeZone The time zone for comparison (default is system time zone)
     *
     * @return bool TRUE if the CRON expression is due to run, FALSE otherwise
     */
    public function isDue(string|DateTimeInterface $currentTime = 'now', ?string $timeZone = null): bool
    {
        // Determine the time zone and normalize current time
        $timeZone = $this->determineTimeZone($currentTime, $timeZone);
        if (is_string($currentTime)) {
            $currentTime = new DateTime($currentTime === 'now' ? 'now' : $currentTime);
        }

        $currentTime = $currentTime instanceof DateTimeImmutable
            ? $currentTime->setTimezone(new DateTimeZone($timeZone))
            : $currentTime->setTimezone(new DateTimeZone($timeZone));

        // Drop seconds and preserve date and minute
        $currentTime = $currentTime instanceof DateTimeImmutable
            ? $currentTime->setTime((int) $currentTime->format('H'), (int) $currentTime->format('i'), 0)
            : $currentTime->setTime((int) $currentTime->format('H'), (int) $currentTime->format('i'), 0);

        try {
            // Check if the current time matches the CRON expression
            return $this->getNextRunDate($currentTime, 0, true)->getTimestamp() === $currentTime->getTimestamp();
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Calculates the next or previous run date of the CRON expression relative to a given date.
     * 
     * This method evaluates the cron expression and finds the next or previous date 
     * that matches the expression, starting from the provided reference date. 
     * It supports options for skipping matches, including the current date if it matches, 
     * and adjusting the direction (forward or backward in time).
     *
     * @param string|DateTimeInterface|null $currentTime The reference date/time for the calculation. 
     *                                                   If null, defaults to the current date/time.
     *                                                   It can be a string (e.g., '2025-03-07 12:00:00') 
     *                                                   or an instance of DateTime/DateTimeImmutable.
     * @param int $nth The number of matching dates to skip before returning the result. 
     *                 For example, 0 will return the first match, 1 skips the first match and returns the second, and so on.
     * @param bool $invert Whether to move backwards in time (true) or forward (false). 
     *                     Default is false (move forward in time).
     * @param bool $allowCurrentDate Whether to return the current date if it matches the CRON expression. 
     *                                Default is false (do not include current date if it matches).
     * @param string|null $timeZone The time zone to use for the calculation. If null, the system's default time zone is used.
     * 
     * @return DateTimeInterface The next or previous matching run date.
     * @throws RuntimeException If an impossible CRON expression is encountered or if the maximum iteration limit is reached.
     */
    protected function getRunDate(string|DateTimeInterface|null $currentTime = null, int $nth = 0, bool $invert = false, bool $allowCurrentDate = false, ?string $timeZone = null): DateTimeInterface
    {
        $timeZone = $this->determineTimeZone($currentTime, $timeZone);

        if ($currentTime instanceof DateTime) {
            $currentDate = clone $currentTime;
        } elseif ($currentTime instanceof DateTimeImmutable) {
            $currentDate = DateTime::createFromFormat('U', $currentTime->format('U'));
        } else {
            $currentDate = new DateTime($currentTime ?: 'now');
        }

        if (!$currentDate) {
            throw new RuntimeException('Invalid date');
        }

        $currentDate->setTimeZone(new DateTimeZone($timeZone))
            ->setTime((int) $currentDate->format('H'), (int) $currentDate->format('i'));

        $nextRun = clone $currentDate;
        $nth = (int) $nth;

        // We don't have to satisfy * or null fields
        $parts = [];
        $fields = [];
        foreach (self::$order as $position) {
            $part = $this->getExpression($position);
            if (null === $part || '*' === $part) {
                continue;
            }
            $parts[$position] = $part;
            $fields[$position] = $this->fields[$position];
        }

        // Set a hard limit to bail on an impossible date
        for ($i = 0; $i < $this->maxIterationCount; $i++) {

            foreach ($parts as $position => $part) {
                $satisfied = false;
                // Get the field object used to validate this part
                $field = $fields[$position];
                // Check if this is singular or a list
                if (strpos($part, ',') === false) {
                    $satisfied = $field->isSatisfiedBy($nextRun, $part);
                } else {
                    foreach (array_map('trim', explode(',', $part)) as $listPart) {
                        if ($field->isSatisfiedBy($nextRun, $listPart)) {
                            $satisfied = true;
                            break;
                        }
                    }
                }

                // If the field is not satisfied, then start over
                if (!$satisfied) {
                    $field->increment($nextRun, $invert, $part);
                    continue 2;
                }
            }

            // Skip this match if needed
            if ((!$allowCurrentDate && $nextRun == $currentDate) || --$nth > -1) {
                $this->fields[0]->increment($nextRun, $invert, isset($parts[0]) ? $parts[0] : null);
                continue;
            }

            return $nextRun;
        }

        throw new RuntimeException('Impossible CRON expression');
    }

    /**
     * Determines the time zone to use for calculations.
     *
     * @param string|DateTimeInterface|null $currentTime The base date/time (could be string or DateTime object)
     * @param string|null $timeZone The time zone to use instead of the default system time zone
     *
     * @return string The name of the time zone to use
     */
    protected function determineTimeZone(string|DateTimeInterface|null $currentTime, ?string $timeZone): string
    {
        if ($timeZone !== null) {
            return $timeZone;
        }

        if ($currentTime instanceof DateTimeInterface) {
            return $currentTime->getTimezone()->getName();
        }

        return date_default_timezone_get();
    }

    /**
     * Returns the string representation of the CRON expression.
     *
     * @return string The CRON expression as a string
     */
    public function __toString(): string
    {
        return strval($this->getExpression());
    }
}