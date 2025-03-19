<?php

namespace Temant\ScheduleManager\Parsers\Tests;

use Temant\ScheduleManager\Parsers\DayOfMonthField;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use PHPUnit\Framework\TestCase;

class DayOfMonthFieldTest extends TestCase
{
    public function testIncrementsDateTimeImmutable(): void
    {
        $d = new DateTimeImmutable('2011-03-15 11:15:00');
        $f = new DayOfMonthField();
        $f->increment($d);
        $this->assertSame('2011-03-16 00:00:00', $d->format('Y-m-d H:i:s'));
    }

    public function testValidatesField(): void
    {
        $f = new DayOfMonthField();

        // Valid cases
        $this->assertTrue($f->validate('1'));
        $this->assertTrue($f->validate('*'));
        $this->assertTrue($f->validate('L'));
        $this->assertTrue($f->validate('5W'));
        $this->assertTrue($f->validate('01'));

        // Invalid cases
        $this->assertFalse($f->validate('5W,L'));
        $this->assertFalse($f->validate('1.'));
        $this->assertFalse($f->validate('32')); // Beyond the max day
        $this->assertFalse($f->validate('0')); // Invalid day
        $this->assertFalse($f->validate('1,5W')); // Invalid list with 'W'
        $this->assertFalse($f->validate('5W,L')); // Invalid list with 'L'
    }

    public function testValidatesSpecialFields(): void
    {
        $f = new DayOfMonthField();

        $this->assertTrue($f->validate('*'));
        $this->assertFalse($f->validate('?'));
        $this->assertFalse($f->validate('5,10W'));
        $this->assertFalse($f->validate('10,L'));
    }

    public function testChecksIfSatisfied(): void
    {
        $f = new DayOfMonthField();

        // Check satisfaction with '?' value
        $this->assertTrue($f->isSatisfiedBy(new DateTime(), '?'));
        $this->assertTrue($f->isSatisfiedBy(new DateTimeImmutable(), '?'));

        // Test with other values
        $this->assertTrue($f->isSatisfiedBy(new DateTime('2025-03-07'), '7'));
        $this->assertFalse($f->isSatisfiedBy(new DateTime('2025-03-07'), '8'));
    }

    public function testIncrementsDate(): void
    {
        $f = new DayOfMonthField();
        $d = new DateTime('2011-03-15 11:15:00');
        $f->increment($d);
        $this->assertSame('2011-03-16 00:00:00', $d->format('Y-m-d H:i:s'));

        $d = new DateTime('2011-03-15 11:15:00');
        $f->increment($d, true);
        $this->assertSame('2011-03-14 23:59:00', $d->format('Y-m-d H:i:s'));
    }

    public function testDoesNotAccept0Date(): void
    {
        $f = new DayOfMonthField();
        $this->assertFalse($f->validate("0"));
    }

    public function testGetNearestWeekday(): void
    {
        // Test valid weekdays (should return the same day)
        $this->assertSame('2025-03-05', $this->getNearestWeekdayDate(2025, 3, 5)->format('Y-m-d')); // Wednesday
        $this->assertSame('2025-03-04', $this->getNearestWeekdayDate(2025, 3, 4)->format('Y-m-d')); // Tuesday

        // Test weekends (Saturday and Sunday should return the closest weekday)

        // Saturday (March 8, 2025) should move to Friday (March 7, 2025)
        $this->assertSame('2025-03-07', $this->getNearestWeekdayDate(2025, 3, 8)->format('Y-m-d'));

        // Sunday (March 9, 2025) should move to Monday (March 10, 2025)
        $this->assertSame('2025-03-10', $this->getNearestWeekdayDate(2025, 3, 9)->format('Y-m-d'));

        // Test edge cases (first and last days of the month)

        // Saturday (March 1, 2025) should move to Friday (February 28, 2025)
        $this->assertSame('2025-02-28', $this->getNearestWeekdayDate(2025, 3, 1)->format('Y-m-d'));

        // Monday (March 31, 2025) should remain unchanged
        $this->assertSame('2025-03-31', $this->getNearestWeekdayDate(2025, 3, 31)->format('Y-m-d'));
    }

    private function getNearestWeekdayDate(int $year, int $month, int $day): DateTimeInterface
    {
        return DayOfMonthField::getNearestWeekday($year, $month, $day);
    }
}