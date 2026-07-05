<?php

declare(strict_types=1);

namespace Kytario\Dto;

use DateTimeImmutable;

/**
 * @implements ArrayFactoryInterface<array{
 *     statusId: int,
 *     name: string,
 *     description?: ?string,
 *     dueDate?: ?string,
 *     startDate?: ?string,
 *     assigneeId?: ?int,
 *     tagIds?: ?list<int>,
 * }>
 */
final readonly class TaskCreateDto implements ArrayFactoryInterface
{
	/** @param list<int>|null $tagIds */
	public function __construct(
		public int $statusId,
		public string $name,
		public ?string $description,
		public ?DateTimeImmutable $dueDate,
		public ?DateTimeImmutable $startDate,
		public ?int $assigneeId,
		public bool $assigneeIdProvided,
		public ?array $tagIds,
	) {
	}

	public static function fromArray(array $data): static
	{
		$dueDate = DateInput::parse($data['dueDate'] ?? null, 'dueDate');
		$startDate = DateInput::parse($data['startDate'] ?? null, 'startDate');

		$assigneeIdProvided = array_key_exists('assigneeId', $data);
		$assigneeId = $assigneeIdProvided ? $data['assigneeId'] : null;

		return new self(
			statusId: $data['statusId'],
			name: $data['name'],
			description: $data['description'] ?? null,
			dueDate: $dueDate,
			startDate: $startDate,
			assigneeId: $assigneeId,
			assigneeIdProvided: $assigneeIdProvided,
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
