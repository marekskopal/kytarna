<?php

declare(strict_types=1);

namespace Kytarna\Dto;

use Kytarna\Model\Entity\Enum\DifficultyEnum;

/**
 * @implements ArrayFactoryInterface<array{
 *     statusId: int,
 *     name: string,
 *     description?: ?string,
 *     tuning?: ?string,
 *     capo?: ?int,
 *     targetTempoBpm?: ?int,
 *     difficulty?: ?string,
 *     tagIds?: ?list<int>,
 * }>
 */
final readonly class LectureUpdateDto implements ArrayFactoryInterface
{
	/** @param list<int>|null $tagIds */
	public function __construct(
		public int $statusId,
		public string $name,
		public ?string $description,
		public ?string $tuning,
		public ?int $capo,
		public ?int $targetTempoBpm,
		public ?DifficultyEnum $difficulty,
		public ?array $tagIds,
	) {
	}

	public static function fromArray(array $data): static
	{
		return new self(
			statusId: $data['statusId'],
			name: $data['name'],
			description: $data['description'] ?? null,
			tuning: $data['tuning'] ?? null,
			capo: $data['capo'] ?? null,
			targetTempoBpm: $data['targetTempoBpm'] ?? null,
			difficulty: LectureCreateDto::parseDifficulty($data['difficulty'] ?? null),
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
