<?php declare(strict_types=1);

namespace Temant\ScheduleManager\Parsers;

use OutOfRangeException;

/**
 * Abstract class representing a CRON expression field.
 * Provides common logic for different CRON field types.
 */
abstract class AbstractField implements FieldInterface
{
    /**
     * The complete range of valid values for this field type.
     * @var array<int>
     */
    protected array $fullRange = [];

    /**
     * Literal values that need to be converted to integers.
     * @var array<string>
     */
    protected array $literals = [];

    /**
     * The starting value of the valid range.
     * @var int
     */
    protected int $rangeStart;

    /**
     * The ending value of the valid range.
     * @var int
     */
    protected int $rangeEnd;

    /**
     * Constructor to initialize the full range based on the defined start and end.
     */
    public function __construct()
    {
        $this->fullRange = range($this->rangeStart, $this->rangeEnd);
    }

    /**
     * Checks if a given value satisfies the condition for the field at the provided date value.
     *
     * @param string $dateValue The date value to check against.
     * @param string $value The CRON expression value to test.
     *
     * @return bool True if the value satisfies the field condition, otherwise false.
     */
    public function isSatisfied(string $dateValue, string $value): bool
    {
        if ($this->isIncrementsOfRanges($value)) {
            return $this->isInIncrementsOfRanges($dateValue, $value);
        } elseif ($this->isRange($value)) {
            return $this->isInRange($dateValue, $value);
        }

        return $value == '*' || $dateValue == $value;
    }

    /**
     * Determines if the given value is a range (e.g., "10-20").
     *
     * @param string $value The CRON expression value to check.
     *
     * @return bool True if the value represents a range, otherwise false.
     */
    public function isRange(string $value): bool
    {
        return strpos($value, '-') !== false;
    }

    /**
     * Determines if the given value is an increment of a range (e.g., "10/2").
     *
     * @param string $value The CRON expression value to check.
     *
     * @return bool True if the value represents an increment of ranges, otherwise false.
     */
    public function isIncrementsOfRanges(string $value): bool
    {
        return strpos($value, '/') !== false;
    }

    /**
     * Checks if the given date value is within the specified range.
     *
     * @param string $dateValue The date value to check.
     * @param string $value The range to check against.
     *
     * @return bool True if the date value is within the range, otherwise false.
     */
    public function isInRange(string $dateValue, string $value): bool
    {
        $parts = array_map(
            function ($value) {
                return $this->convertLiterals(trim($value));
            },
            explode('-', $value, 2)
        );

        return $dateValue >= $parts[0] && $dateValue <= $parts[1];
    }

    /**
     * Checks if the given date value is within an incremented range (e.g., "10/2").
     *
     * @param string $dateValue The date value to check.
     * @param string $value The increment range to check against.
     *
     * @return bool True if the date value is within the incremented range, otherwise false.
     *
     * @throws OutOfRangeException If the range is invalid.
     */
    public function isInIncrementsOfRanges(string $dateValue, string $value): bool
    {
        $chunks = array_map('trim', explode('/', $value, 2));

        if (count($chunks) !== 2 || !ctype_digit($chunks[1])) {
            return false;
        }

        [$range, $stepString] = $chunks;
        $step = (int) $stepString;

        if ($step <= 0) {
            return false;
        }

        if ($range === '*') {
            $range = "{$this->rangeStart}-{$this->rangeEnd}";
        }

        $rangeChunks = explode('-', $range, 2);

        if (!ctype_digit($rangeChunks[0]) || (isset($rangeChunks[1]) && !ctype_digit($rangeChunks[1]))) {
            throw new OutOfRangeException('Range must be numeric');
        }

        $rangeStart = (int) $rangeChunks[0];
        $rangeEnd = isset($rangeChunks[1]) ? (int) $rangeChunks[1] : $rangeStart;

        if (
            $rangeStart < $this->rangeStart || $rangeStart > $this->rangeEnd ||
            $rangeEnd < $this->rangeStart || $rangeEnd > $this->rangeEnd ||
            $rangeStart > $rangeEnd
        ) {
            throw new OutOfRangeException('Invalid range boundaries');
        }

        // Generate the range based on the step size
        $thisRange = ($step >= ($this->rangeEnd - $this->rangeStart))
            ? [$this->fullRange[$step % count($this->fullRange)]]
            : range($rangeStart, $rangeEnd, $step);

        return in_array((int) $dateValue, $thisRange, true);
    }

    /**
     * Returns a range of values for a given CRON expression.
     * Handles ranges, increments, and lists of values.
     *
     * @param string $expression The CRON expression to evaluate.
     * @param int $max The maximum value for the range.
     *
     * @return int[] The expanded range of values.
     */
    public function getRangeForExpression(string $expression, int $max): array
    {
        $expression = $this->convertLiterals($expression);

        // Handle lists (e.g., "1,2,5,10")
        if (str_contains($expression, ',')) {
            return array_values(array_unique(array_merge(
                ...array_map(
                    fn(string $item): array => $this->getRangeForExpression(trim($item), $max),
                    explode(',', $expression)
                )
            )));
        }

        if ($this->isRange($expression) || $this->isIncrementsOfRanges($expression)) {
            if (!$this->isIncrementsOfRanges($expression)) {
                [$offset, $to] = explode('-', $expression);
                $offset = $this->convertLiterals($offset);
                $to = $this->convertLiterals($to);
                $stepSize = 1;
            } else {
                $range = array_map('trim', explode('/', $expression, 2));
                $stepSize = intval($range[1] ?? 0);
                $range = $range[0];
                $range = explode('-', $range, 2);
                $offset = $range[0];
                $to = $range[1] ?? $max;
            }
            $offset = $offset == '*' ? $this->rangeStart : $offset;

            $values = $stepSize >= $this->rangeEnd
                ? [$this->fullRange[(int) $stepSize % (int) count($this->fullRange)]]
                : range($offset, $to, $stepSize);

            sort($values);
        } else {
            $values = [$expression];
        }

        return array_map('intval', $values);
    }

    /**
     * Converts a literal value (e.g., "JAN" to "1") based on the defined literals.
     *
     * @param string $value The literal value to convert.
     * 
     * @return string The converted value.
     */
    protected function convertLiterals(string $value): string
    {
        if (count($this->literals)) {
            $key = array_search($value, $this->literals);
            if ($key !== false) {
                return (string) $key;
            }
        }

        return $value;
    }

    /**
     * Validates if the given CRON expression value is correct for this field.
     *
     * @param string $value The CRON expression value to validate.
     *
     * @return bool True if the value is valid, otherwise false.
     */
    public function validate(string $value): bool
    {
        $value = trim($this->convertLiterals($value));

        // Allow wildcard '*'
        if ($value === '*') {
            return true;
        }

        // Validate increments (e.g., "*/2" or "10/5")
        if (str_contains($value, '/')) {
            [$range, $step] = explode('/', $value, 2);
            return $this->validate($range) && filter_var($step, FILTER_VALIDATE_INT) !== false;
        }

        // Validate lists (e.g., "1,2,3")
        if (str_contains($value, ',')) {
            foreach (explode(',', $value) as $listItem) {
                if (!$this->validate(trim($listItem))) {
                    return false;
                }
            }
            return true;
        }

        // Validate range (e.g., "10-20")
        if (str_contains($value, '-')) {
            $chunks = explode('-', $value, 2);

            if (count($chunks) !== 2 || $chunks[0] === '*' || $chunks[1] === '*') {
                return false;
            }

            [$start, $end] = array_map(fn($v) => trim($this->convertLiterals($v)), $chunks);

            return ctype_digit($start) && ctype_digit($end) && $this->validate($start) && $this->validate($end);
        }

        // Ensure the value is a valid integer (no floats, no non-numeric characters)
        if (!ctype_digit($value)) {
            return false;
        }

        return in_array((int) $value, $this->fullRange, true);
    }
}