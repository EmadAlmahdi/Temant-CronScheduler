<?php declare(strict_types=1);

namespace Temant\ScheduleManager\Parsers\Tests;

use Temant\ScheduleManager\Parsers\MonthField;
use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class MonthFieldTest extends TestCase
{
    private MonthField $field;

    protected function setUp(): void
    {
        $this->field = new MonthField();
    }

    public function testValidatesField(): void
    {
        $this->assertTrue($this->field->validate('12'));
        $this->assertTrue($this->field->validate('*'));
        $this->assertFalse($this->field->validate('*/10,2,1-12'));
        $this->assertFalse($this->field->validate('1.fix-regexp'));
    }

    public function testChecksIfSatisfied(): void
    {
        $this->assertTrue($this->field->isSatisfiedBy(new DateTime(), '?'));
        $this->assertTrue($this->field->isSatisfiedBy(new DateTimeImmutable(), '?'));
    }

    public function testIncrementsDate(): void
    {
        $d = new DateTime('2011-03-15 11:15:00');
        $this->field->increment($d);
        $this->assertSame('2011-04-01 00:00:00', $d->format('Y-m-d H:i:s'));

        $d = new DateTime('2011-03-15 11:15:00');
        $this->field->increment($d, true);
        $this->assertSame('2011-02-28 23:59:00', $d->format('Y-m-d H:i:s'));
    }

    public function testIncrementsDateTimeImmutable(): void
    {
        $d = new DateTimeImmutable('2011-03-15 11:15:00');
        $this->field->increment($d);
        $this->assertSame('2011-04-01 00:00:00', $d->format('Y-m-d H:i:s'));
    }

    public function testIncrementsDateWithThirtyMinuteTimezone(): void
    {
        $tz = date_default_timezone_get();
        date_default_timezone_set('America/St_Johns');
        $d = new DateTime('2011-03-31 11:59:59');
        $this->field->increment($d);
        $this->assertSame('2011-04-01 00:00:00', $d->format('Y-m-d H:i:s'));

        $d = new DateTime('2011-03-15 11:15:00');
        $this->field->increment($d, true);
        $this->assertSame('2011-02-28 23:59:00', $d->format('Y-m-d H:i:s'));
        date_default_timezone_set($tz);
    }

    public function testIncrementsYearAsNeeded(): void
    {
        $d = new DateTime('2011-12-15 00:00:00');
        $this->field->increment($d);
        $this->assertSame('2012-01-01 00:00:00', $d->format('Y-m-d H:i:s'));
    }

    public function testDecrementsYearAsNeeded(): void
    {
        $d = new DateTime('2011-01-15 00:00:00');
        $this->field->increment($d, true);
        $this->assertSame('2010-12-31 23:59:00', $d->format('Y-m-d H:i:s'));
    }
}