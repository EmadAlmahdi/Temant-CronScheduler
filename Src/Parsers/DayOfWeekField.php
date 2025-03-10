<?php declare(strict_types=1);

namespace Temant\ScheduleManager\Parsers;

use DateTime;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Represents the day-of-week field in a cron expression.
 *
 * This class allows specification of cron expressions using a range of constructs:
 * - Wildcard (`*`) - Matches all days of the week.
 * - Step (`/`) - Specifies a step value for repeating days.
 * - List (`','`) - Specifies a list of days.
 * - Range (`-`) - Specifies a range of days.
 * - Wildcard (`?`) - Matches any value (for special cases like "no specific day").
 * - Last (`L`) - Specifies the last occurrence of a weekday in the month.
 * - Hash (`#`) - Specifies the nth occurrence of a weekday in the month (e.g., "2nd Sunday").
 *
 * Days of the week can be represented as:
 * - Numeric values from 0 to 7 (where both 0 and 7 represent Sunday).
 * - Three-letter abbreviations (e.g., "SUN", "MON", "TUE").
 *
 * The class supports expressions like "last Friday" or "the 2nd Sunday" of the month.
 */
class DayOfWeekField extends AbstractField
{
    /**
     * @inheritDoc
     */
    protected int $rangeStart = 0;

    /**
     * @inheritDoc
     */
    protected int $rangeEnd = 7;

    /**
     * @var int[] The valid range of nth weekdays (1 to 5) for hash expressions.
     */
    protected array $nthRange;

    /**
     * @inheritDoc
     */
    protected array $literals = [
        1 => 'MON',
        2 => 'TUE',
        3 => 'WED',
        4 => 'THU',
        5 => 'FRI',
        6 => 'SAT',
        7 => 'SUN'
    ];

    /**
     * Constructor for the DayOfWeekField class.
     *
     * Initializes the valid nth range (1 to 5) for hash expressions and calls the parent constructor.
     */
    public function __construct()
    {
        $this->nthRange = range(1, 5);
        parent::__construct();
    }

    /**
     * Determines if the given date satisfies the cron expression.
     *
     * This method takes into account various constructs like wildcards, ranges, and hash expressions
     * to determine if a given date matches the cron expression.
     *
     * @param DateTime|DateTimeImmutable $date The date to check.
     * @param string $value The cron expression to validate.
     *
     * @return bool True if the date satisfies the expression, false otherwise.
     */
    public function isSatisfiedBy(DateTime|DateTimeImmutable $date, string $value): bool
    {
        // Return true for wildcard '?'
        if ($value === '?') {
            return true;
        }

        // Convert text day of the week values to integers
        $value = $this->convertLiterals($value);

        $currentYear = (int) $date->format('Y');
        $currentMonth = (int) $date->format('m');
        $lastDayOfMonth = (int) $date->format('t');

        // Check if it's the last specific weekday of the month
        if (strpos($value, 'L') !== false) {
            $weekday = (int) $this->convertLiterals(substr($value, 0, strpos($value, 'L'))) % 7;

            $tdate = clone $date;
            $tdate->setDate($currentYear, $currentMonth, $lastDayOfMonth);
            while ($tdate->format('w') !== (string) $weekday) {
                $tdate->modify('-1 day');
            }

            return (int) $date->format('j') === (int) $tdate->format('j');
        }

        // Handle # hash tokens (e.g., "2#3" for the 3rd Tuesday)
        if (strpos($value, '#') !== false) {
            list($weekday, $nth) = explode('#', $value);

            if (!is_numeric($nth)) {
                throw new InvalidArgumentException("Hashed weekdays must be numeric, {$nth} given");
            }

            $nth = (int) $nth;
            $weekday = $this->convertLiterals($weekday);

            // Ensure Sunday (0 or 7) is treated consistently
            if ($weekday === '0' || $weekday === '7') {
                $weekday = 7;
            }

            // Validate the hash fields
            if ($weekday < 1 || $weekday > 7) {
                throw new InvalidArgumentException("Weekday must be between 1 and 7, {$weekday} given");
            }

            if ($nth < 1 || $nth > 5) {
                throw new InvalidArgumentException("There are never more than 5 or less than 1 of a given weekday in a month, {$nth} given");
            }

            if ((int) $date->format('N') !== $weekday) {
                return false;
            }

            $tdate = clone $date;
            $tdate->setDate($currentYear, $currentMonth, 1);
            $dayCount = 0;
            $currentDay = 1;

            while ($currentDay <= $lastDayOfMonth) {
                if ($tdate->format('N') === (string) $weekday) {
                    $dayCount++;
                    if ($dayCount === $nth) {
                        break;
                    }
                }
                $tdate->modify('+1 day');
                $currentDay++;
            }

            return (int) $date->format('j') === $currentDay;
        }

        // Handle day of the week ranges (e.g., "2-5")
        if (strpos($value, '-') !== false) {
            $parts = explode('-', $value);
            $parts[0] = $parts[0] === '7' ? '0' : $parts[0];
            $parts[1] = $parts[1] === '0' ? '7' : $parts[1];
            $value = implode('-', $parts);
        }

        // Determine which Sunday to use: 0 == 7 == Sunday
        $format = in_array('7', str_split($value)) ? 'N' : 'w';
        $fieldValue = $date->format($format);

        return $this->isSatisfied($fieldValue, $value);
    }

    /**
     * @inheritDoc 
     */
    public function increment(DateTime|DateTimeImmutable &$date, bool $invert = false, ?string $parts = null): static
    {
        $date = $date->modify(($invert ? '-1' : '+1') . ' day')
            ->setTime($invert ? 23 : 0, $invert ? 59 : 0, 0);

        return $this;
    }

    /**
     * @inheritDoc 
     */
    public function validate(string $value): bool
    {
        $basicChecks = parent::validate($value);

        if (!$basicChecks) {
            // Handle the # value (e.g., "2#3" for the 3rd Tuesday)
            if (strpos($value, '#') !== false) {
                $chunks = explode('#', $value);
                $chunks[0] = $this->convertLiterals($chunks[0]);

                if (parent::validate($chunks[0]) && is_numeric($chunks[1]) && in_array($chunks[1], $this->nthRange)) {
                    return true;
                }
            }

            // Handle 'L' (last weekday in the month)
            if (preg_match('/^(.*)L$/', $value, $matches)) {
                return $this->validate($matches[1]);
            }

            return false;
        }

        return $basicChecks;
    }
}