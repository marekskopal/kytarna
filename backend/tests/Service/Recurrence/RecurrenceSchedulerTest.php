<?php

declare(strict_types=1);

namespace Ukolio\Tests\Service\Recurrence;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Ukolio\Model\Entity\Enum\RecurrenceCadenceEnum;
use Ukolio\Model\Entity\Enum\RecurrenceEndTypeEnum;
use Ukolio\Model\Entity\TaskRecurrence;
use Ukolio\Service\Recurrence\RecurrenceScheduler;
use Ukolio\Service\Script\Trigger\CronEvaluator;

#[CoversClass(RecurrenceScheduler::class)]
final class RecurrenceSchedulerTest extends TestCase
{
	private RecurrenceScheduler $scheduler;

	protected function setUp(): void
	{
		parent::setUp();

		$this->scheduler = new RecurrenceScheduler(new CronEvaluator());
	}

	public function testDailyIntervalFirstRunSkipsTheAnchorOccurrence(): void
	{
		// Anchor is the current task's occurrence; the first spawn is the next one.
		$recurrence = $this->recurrence(RecurrenceCadenceEnum::Daily, 3, new DateTimeImmutable('2026-06-01'));

		$first = $this->scheduler->computeFirstRun($recurrence, new DateTimeImmutable('2026-06-01 10:00'));

		self::assertNotNull($first);
		self::assertSame('2026-06-04', $first->format('Y-m-d'));
	}

	public function testDailyFirstRunIsNeverInThePast(): void
	{
		$recurrence = $this->recurrence(RecurrenceCadenceEnum::Daily, 1, new DateTimeImmutable('2026-06-01'));

		$first = $this->scheduler->computeFirstRun($recurrence, new DateTimeImmutable('2026-06-10 08:00'));

		self::assertNotNull($first);
		self::assertSame('2026-06-11', $first->format('Y-m-d'));
	}

	public function testWeeklyAlignsToRequestedWeekday(): void
	{
		// 2026-06-01 is a Monday; ask for Wednesday (3) every 2 weeks.
		$recurrence = $this->recurrence(RecurrenceCadenceEnum::Weekly, 2, new DateTimeImmutable('2026-06-01'));
		$recurrence->weekday = 3;

		$first = $this->scheduler->computeFirstRun($recurrence, new DateTimeImmutable('2026-06-01 09:00'));
		self::assertNotNull($first);
		self::assertSame('Wednesday', $first->format('l'));
		self::assertSame('2026-06-03', $first->format('Y-m-d'));

		$next = $this->scheduler->computeNextRun($recurrence, $first);
		self::assertNotNull($next);
		self::assertSame('2026-06-17', $next->format('Y-m-d'));
	}

	public function testMonthlyClampsDayToShorterMonth(): void
	{
		// Day 31 anchored in January; February has no 31st, so it clamps to the 28th.
		$recurrence = $this->recurrence(RecurrenceCadenceEnum::Monthly, 1, new DateTimeImmutable('2026-01-31'));
		$recurrence->dayOfMonth = 31;

		$first = $this->scheduler->computeFirstRun($recurrence, new DateTimeImmutable('2026-01-31 12:00'));

		self::assertNotNull($first);
		self::assertSame('2026-02-28', $first->format('Y-m-d'));
	}

	public function testCronUsesExpressionForNextRun(): void
	{
		$recurrence = $this->recurrence(RecurrenceCadenceEnum::Cron, 1, new DateTimeImmutable('2026-06-01'));
		// Mondays at 09:00
		$recurrence->cronExpression = '0 9 * * 1';

		$first = $this->scheduler->computeFirstRun($recurrence, new DateTimeImmutable('2026-06-02 10:00'));

		self::assertNotNull($first);
		self::assertSame('Monday', $first->format('l'));
		self::assertSame('09:00', $first->format('H:i'));
	}

	public function testEndDateCutoffReturnsNull(): void
	{
		$recurrence = $this->recurrence(RecurrenceCadenceEnum::Daily, 7, new DateTimeImmutable('2026-06-01'));
		$recurrence->endType = RecurrenceEndTypeEnum::OnDate;
		$recurrence->endDate = new DateTimeImmutable('2026-06-05');

		// Next daily-by-7 occurrence after the anchor is 2026-06-08, past the end date.
		$first = $this->scheduler->computeFirstRun($recurrence, new DateTimeImmutable('2026-06-01 09:00'));

		self::assertNull($first);
	}

	private function recurrence(RecurrenceCadenceEnum $cadence, int $interval, DateTimeImmutable $anchor): TaskRecurrence
	{
		// The scheduler reads only the schedule fields, so build a bare entity without DB-backed relations.
		$recurrence = (new ReflectionClass(TaskRecurrence::class))->newInstanceWithoutConstructor();
		$recurrence->cadence = $cadence;
		$recurrence->interval = $interval;
		$recurrence->anchorDate = $anchor;
		$recurrence->endType = RecurrenceEndTypeEnum::Never;

		return $recurrence;
	}
}
