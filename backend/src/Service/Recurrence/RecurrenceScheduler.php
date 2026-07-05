<?php

declare(strict_types=1);

namespace Ukolio\Service\Recurrence;

use DateTimeImmutable;
use Ukolio\Model\Entity\Enum\RecurrenceCadenceEnum;
use Ukolio\Model\Entity\Enum\RecurrenceEndTypeEnum;
use Ukolio\Model\Entity\TaskRecurrence;
use Ukolio\Service\Script\Trigger\CronEvaluatorInterface;

/**
 * Computes spawn timestamps for a recurrence rule. The schedule is *absolute* — each occurrence is
 * anchored to `anchorDate`'s phase (so "every 2 weeks" stays on the same weekday and "1st of month"
 * stays on the 1st) regardless of when the previous task was actually completed.
 */
final readonly class RecurrenceScheduler
{
	private const int LoopGuard = 1200;

	public function __construct(private CronEvaluatorInterface $cronEvaluator)
	{
	}

	/**
	 * First spawn after a rule is (re)configured: the next occurrence strictly after the anchor (the
	 * carrier task *is* the anchor occurrence) that is also not in the past. Null once the end date is
	 * already passed.
	 */
	public function computeFirstRun(TaskRecurrence $recurrence, DateTimeImmutable $now): ?DateTimeImmutable
	{
		if ($recurrence->cadence === RecurrenceCadenceEnum::Cron) {
			$candidate = $this->cronNext($recurrence, $now);

			return $this->withinEnd($recurrence, $candidate);
		}

		$candidate = $this->presetNext($recurrence, $recurrence->anchorDate);
		$guard = 0;
		while ($candidate < $now && $guard < self::LoopGuard) {
			$candidate = $this->presetNext($recurrence, $candidate);
			$guard++;
		}

		return $this->withinEnd($recurrence, $candidate);
	}

	/** Next spawn after the previous one — advances the absolute schedule from `$after`. */
	public function computeNextRun(TaskRecurrence $recurrence, DateTimeImmutable $after): ?DateTimeImmutable
	{
		$candidate = $recurrence->cadence === RecurrenceCadenceEnum::Cron
			? $this->cronNext($recurrence, $after)
			: $this->presetNext($recurrence, $after);

		return $this->withinEnd($recurrence, $candidate);
	}

	private function cronNext(TaskRecurrence $recurrence, DateTimeImmutable $after): DateTimeImmutable
	{
		return $this->cronEvaluator->nextRunDate((string) $recurrence->cronExpression, $after);
	}

	/** First preset occurrence strictly after `$reference`, anchored to the rule's phase. */
	private function presetNext(TaskRecurrence $recurrence, DateTimeImmutable $reference): DateTimeImmutable
	{
		$ref = $reference->setTime(0, 0);

		return match ($recurrence->cadence) {
			RecurrenceCadenceEnum::Daily => $this->uniformNext(
				$recurrence->anchorDate->setTime(0, 0),
				max(1, $recurrence->interval),
				$ref,
			),
			RecurrenceCadenceEnum::Weekly => $this->uniformNext(
				$this->alignToWeekday($recurrence->anchorDate->setTime(0, 0), $recurrence->weekday),
				max(1, $recurrence->interval) * 7,
				$ref,
			),
			RecurrenceCadenceEnum::Monthly => $this->monthlyNext($recurrence, $ref),
			RecurrenceCadenceEnum::Cron => $this->cronNext($recurrence, $reference),
		};
	}

	/** Smallest `anchor + k*periodDays` (k ≥ 0) strictly greater than `$ref`. */
	private function uniformNext(DateTimeImmutable $anchor, int $periodDays, DateTimeImmutable $ref): DateTimeImmutable
	{
		$candidate = $anchor;
		$diffDays = (int) $anchor->diff($ref)->format('%r%a');
		if ($diffDays > 0) {
			$skip = intdiv($diffDays, $periodDays);
			if ($skip > 0) {
				$candidate = $anchor->modify('+' . ($skip * $periodDays) . ' days');
			}
		}

		$guard = 0;
		while ($candidate <= $ref && $guard < self::LoopGuard) {
			$candidate = $candidate->modify('+' . $periodDays . ' days');
			$guard++;
		}

		return $candidate;
	}

	private function monthlyNext(TaskRecurrence $recurrence, DateTimeImmutable $ref): DateTimeImmutable
	{
		$anchor = $recurrence->anchorDate->setTime(0, 0);
		$day = $recurrence->dayOfMonth ?? (int) $anchor->format('j');
		$interval = max(1, $recurrence->interval);

		$anchorMonth = $anchor->modify('first day of this month');
		$refMonth = $ref->modify('first day of this month');
		$monthsDiff = ((int) $refMonth->format('Y') - (int) $anchorMonth->format('Y')) * 12
			+ (int) $refMonth->format('n') - (int) $anchorMonth->format('n');
		$k = $monthsDiff > 0 ? intdiv($monthsDiff, $interval) : 0;

		$guard = 0;
		do {
			$monthStart = $anchorMonth->modify('+' . ($k * $interval) . ' months');
			$candidate = $this->clampDay($monthStart, $day);
			$k++;
			$guard++;
		} while ($candidate <= $ref && $guard < self::LoopGuard);

		return $candidate;
	}

	private function alignToWeekday(DateTimeImmutable $anchor, ?int $weekday): DateTimeImmutable
	{
		if ($weekday === null) {
			return $anchor;
		}

		$delta = ($weekday - (int) $anchor->format('w') + 7) % 7;

		return $delta === 0 ? $anchor : $anchor->modify('+' . $delta . ' days');
	}

	private function clampDay(DateTimeImmutable $monthStart, int $day): DateTimeImmutable
	{
		$daysInMonth = (int) $monthStart->format('t');

		return $monthStart->setDate(
			(int) $monthStart->format('Y'),
			(int) $monthStart->format('n'),
			min($day, $daysInMonth),
		)->setTime(0, 0);
	}

	private function withinEnd(TaskRecurrence $recurrence, DateTimeImmutable $candidate): ?DateTimeImmutable
	{
		if ($recurrence->endType === RecurrenceEndTypeEnum::OnDate
			&& $recurrence->endDate !== null
			&& $candidate->setTime(0, 0) > $recurrence->endDate->setTime(0, 0)
		) {
			return null;
		}

		return $candidate;
	}
}
