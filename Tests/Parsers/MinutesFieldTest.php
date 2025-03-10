<?php declare(strict_types=1);

namespace Temant\ScheduleManager\Parsers\Tests;

use Temant\ScheduleManager\Parsers\MinutesField;
use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class MinutesFieldTest extends TestCase
{
    public function testValidatesField(): void
    {
        $f = new MinutesField();
        $this->assertTrue($f->validate('1'));
        $this->assertTrue($f->validate('*'));
        $this->assertFalse($f->validate('*/3,1,1-12'));
    }

    public function testChecksIfSatisfied(): void
    {
        $f = new MinutesField();
        $this->assertTrue($f->isSatisfiedBy(new DateTime(), '?'));
        $this->assertTrue($f->isSatisfiedBy(new DateTimeImmutable(), '?'));
    }

    public function testIncrementsDate(): void
    {
        $d = new DateTime('2011-03-15 11:15:00');
        $f = new MinutesField();
        $f->increment($d);
        $this->assertSame('2011-03-15 11:16:00', $d->format('Y-m-d H:i:s'));
        $f->increment($d, true);
        $this->assertSame('2011-03-15 11:15:00', $d->format('Y-m-d H:i:s'));
    }

    public function testIncrementsDateTimeImmutable(): void
    {
        $d = new DateTimeImmutable('2011-03-15 11:15:00');
        $f = new MinutesField();
        $f->increment($d);
        $this->assertSame('2011-03-15 11:16:00', $d->format('Y-m-d H:i:s'));
    }

    public function testBadSyntaxesShouldNotValidate(): void
    {
        $f = new MinutesField();
        $this->assertFalse($f->validate('*-1'));
        $this->assertFalse($f->validate('1-2-3'));
        $this->assertFalse($f->validate('-1'));
    }
}
