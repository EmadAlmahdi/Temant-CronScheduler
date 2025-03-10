<?php declare(strict_types=1);

namespace Temant\ScheduleManager\Parsers\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Temant\ScheduleManager\Parsers\DayOfWeekField;
use Temant\ScheduleManager\Parsers\HoursField;
use Temant\ScheduleManager\Parsers\MinutesField;
use Temant\ScheduleManager\Parsers\MonthField;

class AbstractFieldTest extends TestCase
{
    #[DataProvider('rangeProvider')]
    public function testIsRange(string $expression, bool $expected): void
    {
        $f = new DayOfWeekField();
        $this->assertSame($expected, $f->isRange($expression));
    }

    /** @return array<array{string, bool}> */
    public static function rangeProvider(): array
    {
        return [
            ['1-2', true],
            ['2', false],
            ['5-10', true],
            ['*', false],
        ];
    }

    #[DataProvider('incrementsProvider')]
    public function testIsIncrementsOfRanges(string $expression, bool $expected): void
    {
        $f = new DayOfWeekField();
        $this->assertSame($expected, $f->isIncrementsOfRanges($expression));
    }

    /** @return array<array{string, bool}> */
    public static function incrementsProvider(): array
    {
        return [
            ['1-2', false],
            ['1/2', true],
            ['*/2', true],
            ['3-12/2', true],
            ['5-10/3', true],
            ['*/1', true],
        ];
    }

    #[DataProvider('inRangeProvider')]
    public function testIsInRange(string $value, string $range, bool $expected): void
    {
        $f = new DayOfWeekField();
        $this->assertSame($expected, $f->isInRange($value, $range));
    }

    /** @return array<array{string, string, bool}> */
    public static function inRangeProvider(): array
    {
        return [
            ['1', '1-2', true],
            ['2', '1-2', true],
            ['5', '4-12', true],
            ['3', '4-12', false],
            ['13', '4-12', false],
            ['0', '0-5', true],
        ];
    }

    #[DataProvider('satisfiedProvider')]
    public function testIsSatisfied(string $value, string $expression, bool $expected): void
    {
        $f = new DayOfWeekField();
        $this->assertSame($expected, $f->isSatisfied($value, $expression));
    }

    /** @return array<array{string, string, bool}> */
    public static function satisfiedProvider(): array
    {
        return [
            ['12', '3-13', true],
            ['15', '3-7/2', false],
            ['12', '*', true],
            ['12', '12', true],
            ['12', '3-11', false],
            ['12', '11', false],
        ];
    }

    public function testIsInIncrementsOfRangesOnZeroStartRange(): void
    {
        $f = new MinutesField();
        $this->assertTrue($f->isInIncrementsOfRanges('3', '3-59/2'));
        $this->assertTrue($f->isInIncrementsOfRanges('13', '3-59/2'));
        $this->assertFalse($f->isInIncrementsOfRanges('14', '3-59/2'));
        $this->assertFalse($f->isInIncrementsOfRanges('0', '*/0'));
        $this->assertTrue($f->isInIncrementsOfRanges('4', '4/1'));
    }

    public function testIsInIncrementsOfRangesOnOneStartRange(): void
    {
        $f = new MonthField();
        $this->assertTrue($f->isInIncrementsOfRanges('3', '3-12/2'));
        $this->assertFalse($f->isInIncrementsOfRanges('13', '3-12/2'));
        $this->assertTrue($f->isInIncrementsOfRanges('7', '*/3'));
        $this->assertFalse($f->isInIncrementsOfRanges('3', '2-12'));
    }

    public function testAllowRangesAndLists(): void
    {
        $expression = '5-7,11-13';
        $f = new HoursField();
        $this->assertTrue($f->validate($expression));
    }

    public function testGetRangeForExpressionExpandsCorrectly(): void
    {
        $f = new HoursField();
        $this->assertSame([5, 6, 7, 11, 12, 13], $f->getRangeForExpression('5-7,11-13', 23));
        $this->assertSame([0, 6, 12, 18], $f->getRangeForExpression('*/6', 23));
        $this->assertSame([5, 11], $f->getRangeForExpression('5-13/6', 23));
    }
}