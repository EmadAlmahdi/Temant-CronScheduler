<?php declare(strict_types=1);

namespace Temant\ScheduleManager\Parsers\Tests;

use Temant\ScheduleManager\Parsers\DayOfWeekField;
use DateTime;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DayOfWeekFieldTest extends TestCase
{
    private DayOfWeekField $field;

    protected function setUp(): void
    {
        $this->field = new DayOfWeekField();
    }

    #[DataProvider('validCronExpressions')]
    public function testValidateAcceptsValidCronExpressions(string $expression, bool $expected): void
    {
        $this->assertSame($expected, $this->field->validate($expression));
    }

    /** @return array<array{string, bool}> */
    public static function validCronExpressions(): array
    {
        return [
            ['1', true],
            ['01', true],
            ['00', true],
            ['*', true],
            ['SUN-2', true],
            ['1.0', false],
            ['*/3,1,1-12', false]
        ];
    }

    #[DataProvider('wildcardValues')]
    public function testIsSatisfiedByWildcard(string $expression, bool $expected): void
    {
        $this->assertSame($expected, $this->field->isSatisfiedBy(new DateTime(), $expression));
    }

    /** @return array<array{string, bool}> */
    public static function wildcardValues(): array
    {
        return [
            ['?', true],
            ['*', true],
        ];
    }

    public function testIncrementDate(): void
    {
        $d = new DateTime('2011-03-15 11:15:00');
        $this->field->increment($d);
        $this->assertSame('2011-03-16 00:00:00', $d->format('Y-m-d H:i:s'));

        $d = new DateTime('2011-03-15 11:15:00');
        $this->field->increment($d, true);
        $this->assertSame('2011-03-14 23:59:00', $d->format('Y-m-d H:i:s'));
    }

    public function testIncrementDateTimeImmutable(): void
    {
        $d = new DateTimeImmutable('2011-03-15 11:15:00');

        $newDate = $d->modify('+1 day')->setTime(0, 0, 0);
        $this->field->increment($d);
        $this->assertSame($newDate->format('Y-m-d H:i:s'), $d->format('Y-m-d H:i:s'));
    }

    public function testThrowsExceptionForInvalidWeekdayInHash(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Weekday must be between 1 and 7, 12 given');
        $this->field->isSatisfiedBy(new DateTime(), '12#1');
    }

    public function testThrowsExceptionForInvalidNthValueInHash(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('There are never more than 5 or less than 1 of a given weekday in a month');
        $this->field->isSatisfiedBy(new DateTime(), '3#6');
    }

    #[DataProvider('validHashExpressions')]
    public function testValidateWeekendHash(string $expression): void
    {
        $this->assertTrue($this->field->validate($expression));
    }

    /** @return string[][] */
    public static function validHashExpressions(): array
    {
        return [
            ['MON#1'],
            ['TUE#2'],
            ['WED#3'],
            ['THU#4'],
            ['FRI#5'],
            ['SAT#1'],
            ['SUN#3'],
            ['MON#1,MON#3'],
        ];
    }

    #[DataProvider('weekdayEdgeCases')]
    public function testHandlesZeroAndSevenDayOfTheWeekValues(string $date, string $expression, bool $expected): void
    {
        $this->assertSame($expected, $this->field->isSatisfiedBy(new DateTime($date), $expression));
    }

    /** @return array<array{string, string, bool}> */
    public static function weekdayEdgeCases(): array
    {
        return [
            ['2011-09-04 00:00:00', '0-2', true],
            ['2011-09-04 00:00:00', '6-0', true],
            ['2014-04-20 00:00:00', 'SUN', true],
            ['2014-04-20 00:00:00', 'SUN#3', true],
            ['2014-04-20 00:00:00', '0#3', true],
            ['2014-04-20 00:00:00', '7#3', true],
        ];
    }

    #[DataProvider('lastWeekdayOfMonthCases')]
    public function testMatchesLastSpecifiedWeekdayOfMonth(string $date, string $expression, bool $expected): void
    {

        $this->assertSame($expected, $this->field->isSatisfiedBy(new DateTime($date), $expression));
    }

    /** @return array<array{string, string, bool}> */
    public static function lastWeekdayOfMonthCases(): array
    {
        return [
            ['2018-12-28 00:00:00', 'FRIL', true],
            ['2018-12-28 00:00:00', '5L', true],
            ['2018-12-21 00:00:00', 'FRIL', false],
            ['2018-12-21 00:00:00', '5L', false],
        ];
    }

    #[DataProvider('invalidExpressions')]
    public function testInvalidCronExpressions(string $expression): void
    {

        $this->assertFalse($this->field->validate($expression));
    }

    /** @return string[][] */
    public static function invalidExpressions(): array
    {
        return [
            ['mon,'],
            ['mon-'],
            ['*/2,'],
            ['-mon'],
            [',1'],
            ['*-'],
            [',-'],
        ];
    }

    public function testLiteralsExpandProperly(): void
    {
        $this->assertTrue($this->field->validate('MON-FRI'));
        $this->assertSame([1, 2, 3, 4, 5], $this->field->getRangeForExpression('MON-FRI', 7));
    }

    #[DataProvider('lastWeekdayValidationCases')]
    public function testValidatesLastWeekdayExpression(string $expression, bool $expected): void
    {
        $this->assertSame($expected, $this->field->validate($expression));
    }

    /** @return array<array{string, bool}> */
    public static function lastWeekdayValidationCases(): array
    {
        return [
            ['5L', true],    // Sista fredagen
            ['FRIL', true],  // Sista fredagen med bokstav
            ['MONL', true],  // Sista måndagen
            ['7L', true],    // Sista söndagen
            ['0L', true],    // Sista söndagen (om 0 tillåts)
            ['8L', false],   // Ogiltig veckodag
            ['ML', false],   // Ogiltig kombination
            ['L', false],    // Ensamt 'L' är ogiltigt
        ];
    }

    public function testThrowsExceptionForNonNumericNthValueInHash(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Hashed weekdays must be numeric, X given');

        $this->field->isSatisfiedBy(new DateTime(), 'MON#X');
    }

    #[DataProvider('nonMatchingWeekdays')]
    public function testReturnsFalseForNonMatchingWeekdays(string $date, string $expression): void
    {
        $this->assertFalse($this->field->isSatisfiedBy(new DateTime($date), $expression));
    }

    /** @return string[][] */
    public static function nonMatchingWeekdays(): array
    {
        return [
            ['2024-03-04 00:00:00', 'TUE'], // Måndag är inte tisdag
            ['2024-03-06 00:00:00', 'FRI'], // Onsdag är inte fredag
            ['2024-03-10 00:00:00', '3#2'], // Söndag är inte andra tisdagen
        ];
    }
}