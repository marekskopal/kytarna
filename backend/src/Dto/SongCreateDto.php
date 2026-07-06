<?php

declare(strict_types=1);

namespace Kytarna\Dto;

use Kytarna\Model\Entity\Enum\DifficultyEnum;
use Kytarna\Model\Entity\Enum\LearningStatusEnum;

/**
 * @implements ArrayFactoryInterface<array{
 *     name: string,
 *     status?: ?string,
 *     description?: ?string,
 *     tuning?: ?string,
 *     capo?: ?int,
 *     targetTempoBpm?: ?int,
 *     difficulty?: ?string,
 *     authorName?: ?string,
 *     albumName?: ?string,
 *     courseId?: ?int,
 *     tagIds?: ?list<int>,
 * }>
 */
final readonly class SongCreateDto implements ArrayFactoryInterface
{
	/** @param list<int>|null $tagIds */
	public function __construct(
		public string $name,
		public LearningStatusEnum $status,
		public ?string $description,
		public ?string $tuning,
		public ?int $capo,
		public ?int $targetTempoBpm,
		public ?DifficultyEnum $difficulty,
		public ?string $authorName,
		public ?string $albumName,
		public ?int $courseId,
		public ?array $tagIds,
	) {
	}

	public static function fromArray(array $data): static
	{
		return new self(
			name: $data['name'],
			status: LectureCreateDto::parseStatus($data['status'] ?? null) ?? LearningStatusEnum::ToLearn,
			description: $data['description'] ?? null,
			tuning: $data['tuning'] ?? null,
			capo: $data['capo'] ?? null,
			targetTempoBpm: $data['targetTempoBpm'] ?? null,
			difficulty: LectureCreateDto::parseDifficulty($data['difficulty'] ?? null),
			authorName: $data['authorName'] ?? null,
			albumName: $data['albumName'] ?? null,
			courseId: $data['courseId'] ?? null,
			tagIds: LectureCreateDto::parseTagIds($data['tagIds'] ?? null),
		);
	}
}
