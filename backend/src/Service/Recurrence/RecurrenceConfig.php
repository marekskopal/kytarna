<?php

declare(strict_types=1);

namespace Kytario\Service\Recurrence;

use DateTimeImmutable;
use Kytario\Model\Entity\Enum\RecurrenceCadenceEnum;
use Kytario\Model\Entity\Enum\RecurrenceEndTypeEnum;

/** Validated, parsed recurrence settings handed to TaskRecurrenceProvider::set(). */
final readonly class RecurrenceConfig
{
	public function __construct(
		public RecurrenceCadenceEnum $cadence,
		public int $interval,
		public RecurrenceEndTypeEnum $endType,
		public ?int $weekday = null,
		public ?int $dayOfMonth = null,
		public ?string $cronExpression = null,
		public ?DateTimeImmutable $endDate = null,
		public ?int $maxOccurrences = null,
		public ?DateTimeImmutable $anchorDate = null,
	) {
	}
}
