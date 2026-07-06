<?php

declare(strict_types=1);

namespace Kytarna\Dto;

use Kytarna\Model\Entity\Enum\DifficultyEnum;
use Kytarna\Model\Entity\Enum\LearningStatusEnum;
use RuntimeException;

/**
 * @implements ArrayFactoryInterface<array{
 *     status?: ?string,
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
		public LearningStatusEnum $status,
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
			status: LectureCreateDto::parseStatus($data['status'] ?? null)
				?? throw new RuntimeException('status is required.'),
			name: $data['name'],
			description: $data['description'] ?? null,
			tuning: $data['tuning'] ?? null,
			capo: $data['capo'] ?? null,
			targetTempoBpm: $data['targetTempoBpm'] ?? null,
			difficulty: LectureCreateDto::parseDifficulty($data['difficulty'] ?? null),
			tagIds: LectureCreateDto::parseTagIds($data['tagIds'] ?? null),
		);
	}
}
