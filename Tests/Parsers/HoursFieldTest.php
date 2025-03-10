<?php declare(strict_types=1);

namespace Temant\ScheduleManager\Parsers\Tests;

use Temant\ScheduleManager\Parsers\HoursField;
use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase; 

class HoursFieldTest extends TestCase
{
    private HoursField $field;

    protected function setUp(): void
    {
        $this->field = new HoursField();
    }

    public function testValidatesField(): void
    { 
        $this->assertTrue($this->field->validate('1'));
        $this->assertTrue($this->field->validate('00'));
        $this->assertTrue($this->field->validate('01'));
        $this->assertTrue($this->field->validate('*'));
        $this->assertFalse($this->field->validate('*/3,1,1-12'));
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
        $this->assertSame('2011-03-15 12:00:00', $d->format('Y-m-d H:i:s'));

        $d->setTime(11, 15, 0);
        $this->field->increment($d, true);
        $this->assertSame('2011-03-15 10:59:00', $d->format('Y-m-d H:i:s'));
    }
    
    public function testIncrementsDateTimeImmutable(): void
    {
        $d = new DateTimeImmutable('2011-03-15 11:15:00'); 
        $this->field->increment($d);
        $this->assertSame('2011-03-15 12:00:00', $d->format('Y-m-d H:i:s'));
    }

    public function testIncrementsDateWithThirtyMinuteOffsetTimezone(): void
    {
        $tz = date_default_timezone_get();
        date_default_timezone_set('America/St_Johns');
        $d = new DateTime('2011-03-15 11:15:00'); 
        $this->field->increment($d);
        $this->assertSame('2011-03-15 12:00:00', $d->format('Y-m-d H:i:s'));

        $d->setTime(11, 15, 0);
        $this->field->increment($d, true);
        $this->assertSame('2011-03-15 10:59:00', $d->format('Y-m-d H:i:s'));
        date_default_timezone_set($tz);
    }

    public function testIncrementDateWithFifteenMinuteOffsetTimezone(): void
    {
        $tz = date_default_timezone_get();
        date_default_timezone_set('Asia/Kathmandu');
        $d = new DateTime('2011-03-15 11:15:00'); 
        $this->field->increment($d);
        $this->assertSame('2011-03-15 12:00:00', $d->format('Y-m-d H:i:s'));

        $d->setTime(11, 15, 0);
        $this->field->increment($d, true);
        $this->assertSame('2011-03-15 10:59:00', $d->format('Y-m-d H:i:s'));
        date_default_timezone_set($tz);
    }
}