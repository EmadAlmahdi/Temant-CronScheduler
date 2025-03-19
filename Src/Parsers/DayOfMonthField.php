<?php declare(strict_types=1);

namespace Temant\ScheduleManager\Parsers;

use DateTimeImmutable;
use DateTime;
use DateTimeInterface;
use RuntimeException;

/**
 * Day of month field. Allows: * , / - ? L W
 *
 * - 'L' stands for "last" and specifies the last day of the month.
 * - 'W' finds the nearest weekday (Monday–Friday) to a given day.
 *
 * Example: "15W" triggers on the nearest weekday to the 15th.
 *
 * @author Michael Dowling <mtdowling@gmail.com>
 */
class DayOfMonthField extends AbstractField
{
    /**
     * @inheritDoc
     */
    protected int $rangeStart = 1;

    /**
     * @inheritDoc
     */
    protected int $rangeEnd = 31;

    /**
     * Get the nearest weekday for a given day in a month.
     *
     * If the target day falls on a weekend, this function finds the closest
     * weekday (Monday–Friday). It prioritizes moving backward first (Friday → Thursday).
     *
     * @param int $currentYear  The current year.
     * @param int $currentMonth The current month.
     * @param int $targetDay    The target day of the month.
     *
     * @return DateTimeInterface The nearest valid weekday.
     *
     * @throws RuntimeException If the date creation fails.
     */
    public static function getNearestWeekday(int $year, int $month, int $day): DateTimeInterface
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', sprintf('%04d-%02d-%02d', $year, $month, $day));

        if ($date === false) {
            throw new RuntimeException("Invalid date: $year-$month-$day");
        }

        // Weekday (1 = Monday, 7 = Sunday)
        $weekday = (int) $date->format('N');

        // Adjust only if it's Saturday (6) or Sunday (7)
        $adjustments = [6 => '-1 day', 7 => '+1 day'];

        return $date->modify($adjustments[$weekday] ?? '0 day');
    }

    /**
     * @inheritDoc
     */
    public function isSatisfiedBy(DateTime|DateTimeImmutable $date, string $value): bool
    {
        // ? states that the field value is to be skipped
        if ($value == '?') {
            return true;
        }

        $fieldValue = $date->format('d');

        // Check to see if this is the last day of the month
        if ($value == 'L') {
            return $fieldValue == $date->format('t');
        }

        // Check to see if this is the nearest weekday to a particular value
        if (strpos($value, 'W')) {
            // Parse the target day and cast to int
            $targetDay = (int) substr($value, 0, intval(strpos($value, 'W')));

            return $date->format('j') == self::getNearestWeekday(
                (int) $date->format('Y'),
                (int) $date->format('m'),
                $targetDay
            )->format('j');
        }

        return $this->isSatisfied($date->format('d'), $value);
    }

    /**
     * @inheritDoc
     */
    public function increment(DateTime|DateTimeImmutable &$date, bool $invert = false, ?string $parts = null): static
    {
        $date = $invert
            ? $date->modify('previous day')->setTime(23, 59)
            : $date->modify('next day')->setTime(0, 0);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function validate(string $value): bool
    {
        $basicChecks = parent::validate($value);

        // Validate that a list don't have W or L
        if (strpos($value, ',') !== false && (strpos($value, 'W') !== false || strpos($value, 'L') !== false)) {
            return false;
        }

        if (!$basicChecks) {

            if ($value === 'L') {
                return true;
            }

            if (preg_match('/^(.*)W$/', $value, $matches)) {
                return $this->validate($matches[1]);
            }

            return false;
        }

        return $basicChecks;
    }
}