<?php declare(strict_types=1);

namespace Temant\ScheduleManager\Parsers;

use DateTime;
use DateTimeImmutable;

/**
 * Minutes field.  Allows: * , / -
 */
class MinutesField extends AbstractField
{
    /**
     * @inheritDoc
     */
    protected int $rangeStart = 0;

    /**
     * @inheritDoc
     */
    protected int $rangeEnd = 59;

    /**
     * @inheritDoc
     */
    public function isSatisfiedBy(DateTime|DateTimeImmutable $date, string $value): bool
    {
        if ($value == '?') {
            return true;
        }

        return $this->isSatisfied($date->format('i'), $value);
    }

    /**
     * {@inheritDoc} 
     */
    public function increment(DateTime|DateTimeImmutable &$date, $invert = false, ?string $parts = null): static
    {
        if ($parts === null) {
            $date = $date->modify(($invert ? '-' : '+') . '1 minute');
            return $this;
        }

        $parts = strpos($parts, ',') !== false ? explode(',', $parts) : [$parts];
        $minutes = [];
        foreach ($parts as $part) {
            $minutes = array_merge($minutes, $this->getRangeForExpression($part, 59));
        }

        $current_minute = $date->format('i');
        $position = $invert ? count($minutes) - 1 : 0;
        if (count($minutes) > 1) {
            for ($i = 0; $i < count($minutes) - 1; $i++) {
                if (
                    (!$invert && $current_minute >= $minutes[$i] && $current_minute < $minutes[$i + 1]) ||
                    ($invert && $current_minute > $minutes[$i] && $current_minute <= $minutes[$i + 1])
                ) {
                    $position = $invert ? $i : $i + 1;
                    break;
                }
            }
        }

        if ((!$invert && $current_minute >= $minutes[$position]) || ($invert && $current_minute <= $minutes[$position])) {
            $date = $date->modify(($invert ? '-' : '+') . '1 hour');
            $date = $date->setTime(intval($date->format('H')), $invert ? 59 : 0);
        } else {
            $date = $date->setTime(intval($date->format('H')), intval($minutes[$position]));
        }

        return $this;
    }
}
