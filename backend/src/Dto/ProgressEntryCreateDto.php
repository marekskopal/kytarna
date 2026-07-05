<?php

declare(strict_types=1);

namespace Kytario\Dto;

use DateTimeImmutable;

/**
 * @implements ArrayFactoryInterface<array{
 *     practicedAt?: ?string,
 *     note?: ?string,
 *     tempoBpm?: ?int,
 *     durationMinutes?: ?int,
 * }>
 */
final readonly class ProgressEntryCreateDto implements ArrayFactoryInterface
{
	public function __construct(
		public DateTimeImmutable $practicedAt,
		public ?string $note,
		public ?int $tempoBpm,
		public ?int $durationMinutes,
	) {
	}

	public static function fromArray(array $data): static
	{
		// practicedAt defaults to today when omitted.
		$practicedAt = DateInput::parse($data['practicedAt'] ?? null, 'practicedAt')
			?? new DateTimeImmutable('today');

		return new self(
			practicedAt: $practicedAt,
			note: $data['note'] ?? null,
			tempoBpm: $data['tempoBpm'] ?? null,
			durationMinutes: $data['durationMinutes'] ?? null,
		);
	}
}
