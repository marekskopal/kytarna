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
final readonly class ProgressEntryUpdateDto implements ArrayFactoryInterface
{
	public function __construct(
		public ?DateTimeImmutable $practicedAt,
		public ?string $note,
		public ?int $tempoBpm,
		public ?int $durationMinutes,
	) {
	}

	public static function fromArray(array $data): static
	{
		return new self(
			practicedAt: DateInput::parse($data['practicedAt'] ?? null, 'practicedAt'),
			note: $data['note'] ?? null,
			tempoBpm: $data['tempoBpm'] ?? null,
			durationMinutes: $data['durationMinutes'] ?? null,
		);
	}
}
