<?php

declare(strict_types=1);

namespace Ukolio\Dto;

use DateTimeImmutable;
use RuntimeException;
use Ukolio\Model\Entity\Enum\RecurrenceCadenceEnum;
use Ukolio\Model\Entity\Enum\RecurrenceEndTypeEnum;
use Ukolio\Service\Recurrence\RecurrenceConfig;

/**
 * @implements ArrayFactoryInterface<array{
 *     cadence?: string,
 *     interval?: int,
 *     weekday?: ?int,
 *     dayOfMonth?: ?int,
 *     cronExpression?: ?string,
 *     endType?: string,
 *     endDate?: ?string,
 *     maxOccurrences?: ?int,
 *     anchorDate?: ?string,
 * }>
 */
final readonly class TaskRecurrenceWriteDto implements ArrayFactoryInterface
{
	public function __construct(
		public string $cadence,
		public int $interval,
		public string $endType,
		public ?int $weekday,
		public ?int $dayOfMonth,
		public ?string $cronExpression,
		public ?DateTimeImmutable $endDate,
		public ?int $maxOccurrences,
		public ?DateTimeImmutable $anchorDate,
	) {
	}

	public static function fromArray(array $data): static
	{
		return new self(
			cadence: $data['cadence'] ?? '',
			interval: $data['interval'] ?? 1,
			endType: $data['endType'] ?? RecurrenceEndTypeEnum::Never->value,
			weekday: $data['weekday'] ?? null,
			dayOfMonth: $data['dayOfMonth'] ?? null,
			cronExpression: ($data['cronExpression'] ?? '') !== '' ? $data['cronExpression'] : null,
			endDate: self::parseDate($data['endDate'] ?? null),
			maxOccurrences: $data['maxOccurrences'] ?? null,
			anchorDate: self::parseDate($data['anchorDate'] ?? null),
		);
	}

	/** Resolves enum strings, throwing RuntimeException (→ 422) on an unknown value. */
	public function toConfig(): RecurrenceConfig
	{
		$cadence = RecurrenceCadenceEnum::tryFrom($this->cadence)
			?? throw new RuntimeException('Unknown recurrence cadence: ' . $this->cadence);
		$endType = RecurrenceEndTypeEnum::tryFrom($this->endType)
			?? throw new RuntimeException('Unknown recurrence end type: ' . $this->endType);

		return new RecurrenceConfig(
			cadence: $cadence,
			interval: $this->interval,
			endType: $endType,
			weekday: $this->weekday,
			dayOfMonth: $this->dayOfMonth,
			cronExpression: $this->cronExpression,
			endDate: $this->endDate,
			maxOccurrences: $this->maxOccurrences,
			anchorDate: $this->anchorDate,
		);
	}

	private static function parseDate(mixed $value): ?DateTimeImmutable
	{
		if (!is_string($value) || $value === '') {
			return null;
		}

		return new DateTimeImmutable($value);
	}
}
