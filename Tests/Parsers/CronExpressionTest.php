<?php declare(strict_types=1);

namespace Temant\ScheduleManager\Parsers\Tests;

use Temant\ScheduleManager\Parsers\CronExpression;
use Temant\ScheduleManager\Parsers\MonthField;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CronExpressionTest extends TestCase
{
    public function testFactoryRecognizesTemplates(): void
    {
        $this->assertSame('0 0 1 1 *', new CronExpression('@annually')->getExpression());
        $this->assertSame('0 0 1 1 *', new CronExpression('@yearly')->getExpression());
        $this->assertSame('0 0 * * 0', new CronExpression('@weekly')->getExpression());
    }

    public function testParsesCronSchedule(): void
    {
        // '2010-09-10 12:00:00'
        $cron = new CronExpression('1 2-4 * 4,5,6 */3');
        $this->assertSame('1', $cron->getExpression(CronExpression::MINUTE));
        $this->assertSame('2-4', $cron->getExpression(CronExpression::HOUR));
        $this->assertSame('*', $cron->getExpression(CronExpression::DAY));
        $this->assertSame('4,5,6', $cron->getExpression(CronExpression::MONTH));
        $this->assertSame('*/3', $cron->getExpression(CronExpression::WEEKDAY));
        $this->assertSame('1 2-4 * 4,5,6 */3', $cron->getExpression());
        $this->assertSame('1 2-4 * 4,5,6 */3', (string) $cron);
        $this->assertNull($cron->getExpression(99));
    }

    public function testParsesCronScheduleThrowsAnException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid CRON field value A at position 0');

        new CronExpression('A 1 2 3 4');
    }

    /**
     * @param string $schedule
     * @param string[] $expected
     */
    #[DataProvider('scheduleWithDifferentSeparatorsProvider')]
    public function testParsesCronScheduleWithAnySpaceCharsAsSeparators(string $schedule, array $expected): void
    {
        $cron = new CronExpression($schedule);
        $this->assertSame($expected[0], $cron->getExpression(CronExpression::MINUTE));
        $this->assertSame($expected[1], $cron->getExpression(CronExpression::HOUR));
        $this->assertSame($expected[2], $cron->getExpression(CronExpression::DAY));
        $this->assertSame($expected[3], $cron->getExpression(CronExpression::MONTH));
        $this->assertSame($expected[4], $cron->getExpression(CronExpression::WEEKDAY));
    }

    /** @return array<array{string, string[]}> */
    public static function scheduleWithDifferentSeparatorsProvider(): array
    {
        return [
            ["*\t*\t*\t*\t*\t", ['*', '*', '*', '*', '*', '*']],
            ["*  *  *  *  *  ", ['*', '*', '*', '*', '*', '*']],
            ["* \t * \t * \t * \t * \t", ['*', '*', '*', '*', '*', '*']],
            ["*\t \t*\t \t*\t \t*\t \t*\t \t", ['*', '*', '*', '*', '*', '*']],
        ];
    }

    public function testInvalidCronsWillFail(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // Only four values
        new CronExpression('* * * 1');
    }

    public function testInvalidPartsWillFail(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // Only four values
        $cron = new CronExpression('* * * * *');
        $cron->setPart(1, 'abc');
    }

    public function testIsDueHandlesDifferentDates(): void
    {
        $cron = new CronExpression('* * * * *');
        $this->assertTrue($cron->isDue());
        $this->assertTrue($cron->isDue('now'));
        $this->assertTrue($cron->isDue(new DateTime('now')));
        $this->assertTrue($cron->isDue(date('Y-m-d H:i')));
        $this->assertTrue($cron->isDue(new DateTimeImmutable('now')));
    }

    public function testIsDueHandlesDifferentDefaultTimezones(): void
    {
        $originalTimezone = date_default_timezone_get();
        $cron = new CronExpression('0 15 * * 3'); //Wednesday at 15:00
        $date = '2014-01-01 15:00'; //Wednesday

        date_default_timezone_set('UTC');
        $this->assertTrue($cron->isDue(new DateTime($date), 'UTC'));
        $this->assertFalse($cron->isDue(new DateTime($date), 'Europe/Amsterdam'));
        $this->assertFalse($cron->isDue(new DateTime($date), 'Asia/Tokyo'));

        date_default_timezone_set('Europe/Amsterdam');
        $this->assertFalse($cron->isDue(new DateTime($date), 'UTC'));
        $this->assertTrue($cron->isDue(new DateTime($date), 'Europe/Amsterdam'));
        $this->assertFalse($cron->isDue(new DateTime($date), 'Asia/Tokyo'));

        date_default_timezone_set('Asia/Tokyo');
        $this->assertFalse($cron->isDue(new DateTime($date), 'UTC'));
        $this->assertFalse($cron->isDue(new DateTime($date), 'Europe/Amsterdam'));
        $this->assertTrue($cron->isDue(new DateTime($date), 'Asia/Tokyo'));

        date_default_timezone_set($originalTimezone);
    }

    public function testIsDueHandlesDifferentSuppliedTimezones(): void
    {
        $cron = new CronExpression('0 15 * * 3'); //Wednesday at 15:00
        $date = '2014-01-01 15:00'; //Wednesday

        $this->assertTrue($cron->isDue(new DateTime($date, new DateTimeZone('UTC')), 'UTC'));
        $this->assertFalse($cron->isDue(new DateTime($date, new DateTimeZone('UTC')), 'Europe/Amsterdam'));
        $this->assertFalse($cron->isDue(new DateTime($date, new DateTimeZone('UTC')), 'Asia/Tokyo'));

        $this->assertFalse($cron->isDue(new DateTime($date, new DateTimeZone('Europe/Amsterdam')), 'UTC'));
        $this->assertTrue($cron->isDue(new DateTime($date, new DateTimeZone('Europe/Amsterdam')), 'Europe/Amsterdam'));
        $this->assertFalse($cron->isDue(new DateTime($date, new DateTimeZone('Europe/Amsterdam')), 'Asia/Tokyo'));

        $this->assertFalse($cron->isDue(new DateTime($date, new DateTimeZone('Asia/Tokyo')), 'UTC'));
        $this->assertFalse($cron->isDue(new DateTime($date, new DateTimeZone('Asia/Tokyo')), 'Europe/Amsterdam'));
        $this->assertTrue($cron->isDue(new DateTime($date, new DateTimeZone('Asia/Tokyo')), 'Asia/Tokyo'));
    }

    public function testIsDueHandlesDifferentTimezonesAsArgument(): void
    {
        $cron = new CronExpression('0 15 * * 3'); //Wednesday at 15:00
        $date = '2014-01-01 15:00'; //Wednesday
        $utc = new DateTimeZone('UTC');
        $amsterdam = new DateTimeZone('Europe/Amsterdam');
        $tokyo = new DateTimeZone('Asia/Tokyo');
        $this->assertTrue($cron->isDue(new DateTime($date, $utc), 'UTC'));
        $this->assertFalse($cron->isDue(new DateTime($date, $amsterdam), 'UTC'));
        $this->assertFalse($cron->isDue(new DateTime($date, $tokyo), 'UTC'));
        $this->assertFalse($cron->isDue(new DateTime($date, $utc), 'Europe/Amsterdam'));
        $this->assertTrue($cron->isDue(new DateTime($date, $amsterdam), 'Europe/Amsterdam'));
        $this->assertFalse($cron->isDue(new DateTime($date, $tokyo), 'Europe/Amsterdam'));
        $this->assertFalse($cron->isDue(new DateTime($date, $utc), 'Asia/Tokyo'));
        $this->assertFalse($cron->isDue(new DateTime($date, $amsterdam), 'Asia/Tokyo'));
        $this->assertTrue($cron->isDue(new DateTime($date, $tokyo), 'Asia/Tokyo'));
    }

    public function testRecognisesTimezonesAsPartOfDateTime(): void
    {
        $cron = new CronExpression("0 7 * * *");
        $tzCron = "America/New_York";
        $tzServer = new DateTimeZone("Europe/London");

        // Change DateTime to DateTimeImmutable
        $dtCurrent = DateTimeImmutable::createFromFormat("!Y-m-d H:i:s", "2017-10-17 10:00:00", $tzServer);
        $dtCurrent = is_bool($dtCurrent) ? null : $dtCurrent;
        $dtPrev = $cron->getPreviousRunDate($dtCurrent, 0, true, $tzCron);
        $this->assertEquals('1508151600 : 2017-10-16T07:00:00-04:00 : America/New_York', $dtPrev->format("U \: c \: e"));

        $dtCurrent = DateTimeImmutable::createFromFormat("!Y-m-d H:i:s", "2017-10-17 10:00:00", $tzServer);
        $dtCurrent = is_bool($dtCurrent) ? null : $dtCurrent;
        $dtPrev = $cron->getPreviousRunDate($dtCurrent, 0, true, $tzCron);
        $this->assertEquals('1508151600 : 2017-10-16T07:00:00-04:00 : America/New_York', $dtPrev->format("U \: c \: e"));

        $dtCurrent = DateTimeImmutable::createFromFormat("!Y-m-d H:i:s", "2017-10-17 10:00:00", $tzServer);
        $dtCurrent = is_bool($dtCurrent) ? null : $dtCurrent;
        $dtPrev = $cron->getPreviousRunDate($dtCurrent?->format("c"), 0, true, $tzCron);
        $this->assertEquals('1508151600 : 2017-10-16T07:00:00-04:00 : America/New_York', $dtPrev->format("U \: c \: e"));

        $dtCurrent = DateTimeImmutable::createFromFormat("!Y-m-d H:i:s", "2017-10-17 10:00:00", $tzServer);
        $dtCurrent = is_bool($dtCurrent) ? null : $dtCurrent;
        $dtPrev = $cron->getPreviousRunDate($dtCurrent?->format("\@U"), 0, true, $tzCron);
        $this->assertEquals('1508151600 : 2017-10-16T07:00:00-04:00 : America/New_York', $dtPrev->format("U \: c \: e"));
    }

    public function testCanGetPreviousRunDates(): void
    {
        $cron = new CronExpression('* * * * *');
        $next = $cron->getNextRunDate('now');
        $two = $cron->getNextRunDate('now', 1);
        $this->assertEquals($next, $cron->getPreviousRunDate($two));

        $cron = new CronExpression('* */2 * * *');
        $next = $cron->getNextRunDate('now');
        $two = $cron->getNextRunDate('now', 1);
        $this->assertEquals($next, $cron->getPreviousRunDate($two));

        $cron = new CronExpression('* * * */2 *');
        $next = $cron->getNextRunDate('now');
        $two = $cron->getNextRunDate('now', 1);
        $this->assertEquals($next, $cron->getPreviousRunDate($two));
    }

    public function testProvidesMultipleRunDates(): void
    {
        $cron = new CronExpression('*/2 * * * *');
        $this->assertEquals(array(
            new DateTime('2008-11-09 00:00:00'),
            new DateTime('2008-11-09 00:02:00'),
            new DateTime('2008-11-09 00:04:00'),
            new DateTime('2008-11-09 00:06:00')
        ), $cron->getMultipleRunDates(4, '2008-11-09 00:00:00', false, true));
    }

    public function testProvidesMultipleRunDatesForTheFarFuture(): void
    {
        // Fails with the default 1000 iteration limit
        $cron = new CronExpression('0 0 12 1 *');
        $cron->setMaxIterationCount(2000);
        $this->assertEquals([
            new DateTime('2016-01-12 00:00:00'),
            new DateTime('2017-01-12 00:00:00'),
            new DateTime('2018-01-12 00:00:00'),
            new DateTime('2019-01-12 00:00:00'),
            new DateTime('2020-01-12 00:00:00'),
            new DateTime('2021-01-12 00:00:00'),
            new DateTime('2022-01-12 00:00:00'),
            new DateTime('2023-01-12 00:00:00'),
            new DateTime('2024-01-12 00:00:00'),
        ], $cron->getMultipleRunDates(9, '2015-04-28 00:00:00', false, true));
    }

    public function testCanIterateOverNextRuns(): void
    {
        $cron = new CronExpression('@weekly');
        $nextRun = $cron->getNextRunDate("2008-11-09 08:00:00");
        $this->assertEquals($nextRun, new DateTime("2008-11-16 00:00:00"));

        // true is cast to 1
        $nextRun = $cron->getNextRunDate("2008-11-09 00:00:00", 1, true);
        $this->assertEquals($nextRun, new DateTime("2008-11-16 00:00:00"));

        // You can iterate over them
        $nextRun = $cron->getNextRunDate($cron->getNextRunDate("2008-11-09 00:00:00", 1, true), 1, true);
        $this->assertEquals($nextRun, new DateTime("2008-11-23 00:00:00"));

        // You can skip more than one
        $nextRun = $cron->getNextRunDate("2008-11-09 00:00:00", 2, true);
        $this->assertEquals($nextRun, new DateTime("2008-11-23 00:00:00"));
        $nextRun = $cron->getNextRunDate("2008-11-09 00:00:00", 3, true);
        $this->assertEquals($nextRun, new DateTime("2008-11-30 00:00:00"));
    }

    public function testGetRunDateHandlesDifferentDates(): void
    {
        $cron = new CronExpression('@weekly');
        $date = new DateTime("2019-03-10 00:00:00");
        $this->assertEquals($date, $cron->getNextRunDate("2019-03-03 08:00:00"));
        $this->assertEquals($date, $cron->getNextRunDate(new DateTime("2019-03-03 08:00:00")));
        $this->assertEquals($date, $cron->getNextRunDate(new DateTimeImmutable("2019-03-03 08:00:00")));
    }

    public function testSkipsCurrentDateByDefault(): void
    {
        $cron = new CronExpression('* * * * *');
        $current = new DateTime('now');
        $next = $cron->getNextRunDate($current);
        $nextPrev = $cron->getPreviousRunDate($next);
        $this->assertSame($current->format('Y-m-d H:i:00'), $nextPrev->format('Y-m-d H:i:s'));
    }

    public function testStripsForSeconds(): void
    {
        $cron = new CronExpression('* * * * *');
        $current = new DateTime('2011-09-27 10:10:54');
        $this->assertSame('2011-09-27 10:11:00', $cron->getNextRunDate($current)->format('Y-m-d H:i:s'));
    }

    public function testFixesPhpBugInDateIntervalMonth(): void
    {
        $cron = new CronExpression('0 0 27 JAN *');
        $this->assertSame('2011-01-27 00:00:00', $cron->getPreviousRunDate('2011-08-22 00:00:00')->format('Y-m-d H:i:s'));
    }

    public function testKeepOriginalTime(): void
    {
        $now = new DateTime;
        $strNow = $now->format(DateTime::ISO8601);
        $cron = new CronExpression('0 0 * * *');
        $cron->getPreviousRunDate($now);
        $this->assertSame($strNow, $now->format(DateTime::ISO8601));
    }

    public function testValidationWorks(): void
    {
        $this->assertFalse(CronExpression::isValidExpression('* * * 1'));
        $this->assertTrue(CronExpression::isValidExpression('* * * * 1'));
        $this->assertFalse(CronExpression::isValidExpression("* * * 13 * "));
        $this->assertFalse(CronExpression::isValidExpression('90 * * * *'));
        $this->assertFalse(CronExpression::isValidExpression("0 24 1 12 0"));
        $this->assertFalse(CronExpression::isValidExpression('990 14 * * mon-fri0345345'));
        $this->assertTrue(CronExpression::isValidExpression('2,17,35,47 5-7,11-13 * * *'));
    }

    public function testDoubleZeroIsValid(): void
    {
        $this->assertTrue(CronExpression::isValidExpression('00 * * * *'));
        $this->assertTrue(CronExpression::isValidExpression('01 * * * *'));
        $this->assertTrue(CronExpression::isValidExpression('* 00 * * *'));
        $this->assertTrue(CronExpression::isValidExpression('* 01 * * *'));
        $e = new CronExpression('00 * * * *');
        $this->assertTrue($e->isDue(new DateTime('2014-04-07 00:00:00')));
        $e = new CronExpression('01 * * * *');
        $this->assertTrue($e->isDue(new DateTime('2014-04-07 00:01:00')));
        $e = new CronExpression('* 00 * * *');
        $this->assertTrue($e->isDue(new DateTime('2014-04-07 00:00:00')));
        $e = new CronExpression('* 01 * * *');
        $this->assertTrue($e->isDue(new DateTime('2014-04-07 01:00:00')));
    }

    public function testRangesWrapAroundWithLargeSteps(): void
    {
        $f = new MonthField();
        $this->assertTrue($f->validate('*/123'));
        $this->assertSame([4], $f->getRangeForExpression('*/123', 12));

        $e = new CronExpression('* * * */123 *');
        $this->assertTrue($e->isDue(new DateTime('2014-04-07 00:00:00')));

        $nextRunDate = $e->getNextRunDate(new DateTime('2014-04-07 00:00:00'));
        $this->assertSame('2014-04-07 00:01:00', $nextRunDate->format('Y-m-d H:i:s'));

        $nextRunDate = $e->getNextRunDate(new DateTime('2014-05-07 00:00:00'));
        $this->assertSame('2015-04-01 00:00:00', $nextRunDate->format('Y-m-d H:i:s'));
    }

    public function testFieldPositionIsHumanAdjusted(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("6 is not a valid position");
        $e = new CronExpression('0 * * * * ? *');
    }
}