<?php

declare(strict_types=1);

namespace Kytario\Dto;

use DateTimeImmutable;

/**
 * @implements ArrayFactoryInterface<array{
 *     statusId: int,
 *     name: string,
 *     description?: ?string,
 *     startDate?: ?string,
 *     tagIds?: ?list<int>,
 * }>
 */
final readonly class LectureCreateDto implements ArrayFactoryInterface
{
	/** @param list<int>|null $tagIds */
	public function __construct(
		public int $statusId,
		public string $name,
		public ?string $description,
		public ?DateTimeImmutable $startDate,
		public ?array $tagIds,
	) {
	}

	public static function fromArray(array $data): static
	{
		return new self(
			statusId: $data['statusId'],
			name: $data['name'],
			description: $data['description'] ?? null,
			startDate: DateInput::parse($data['startDate'] ?? null, 'startDate'),
			tagIds: self::parseTagIds($data['tagIds'] ?? null),
		);
	}

	/**
	 * @param list<int>|null $raw
	 * @return list<int>|null
	 */
	private static function parseTagIds(?array $raw): ?array
	{
		if ($raw === null) {
			return null;
		}
		return array_values(array_unique(array_map('intval', $raw)));
	}
}
