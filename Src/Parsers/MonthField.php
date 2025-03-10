<?php declare(strict_types=1);

namespace Temant\ScheduleManager\Parsers;

use DateTime;
use DateTimeImmutable;

/**
 * Month field.  Allows: * , / -
 */
class MonthField extends AbstractField
{
    /**
     * @inheritDoc
     */
    protected int $rangeStart = 1;

    /**
     * @inheritDoc
     */
    protected int $rangeEnd = 12;

    /**
     * @inheritDoc
     */
    protected array $literals = [
        1 => 'JAN',
        2 => 'FEB',
        3 => 'MAR',
        4 => 'APR',
        5 => 'MAY',
        6 => 'JUN',
        7 => 'JUL',
        8 => 'AUG',
        9 => 'SEP',
        10 => 'OCT',
        11 => 'NOV',
        12 => 'DEC'
    ];

    /**
     * @inheritDoc
     */
    public function isSatisfiedBy(DateTime|DateTimeImmutable $date, string $value): bool
    {
        if ($value == '?') {
            return true;
        }

        $value = $this->convertLiterals($value);

        return $this->isSatisfied($date->format('m'), $value);
    }

    /**
     * @inheritDoc
     */
    public function increment(DateTime|DateTimeImmutable &$date, bool $invert = false, ?string $parts = null): static
    {
        if ($invert) {
            $date = $date->modify('last day of previous month')->setTime(23, 59);
        } else {
            $date = $date->modify('first day of next month')->setTime(0, 0);
        }

        return $this;
    }


}
