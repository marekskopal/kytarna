<?php

declare(strict_types=1);

namespace Kytarna\Dto;

use Kytarna\Model\Entity\Lecture;
use const DATE_ATOM;

final readonly class LectureListItemDto
{
	/** @param list<int> $tagIds */
	public function __construct(
		public int $id,
		public string $code,
		public int $courseId,
		public string $courseName,
		public string $status,
		public string $name,
		public ?string $description,
		public ?string $tuning,
		public ?int $capo,
		public ?int $targetTempoBpm,
		public ?string $difficulty,
		public int $position,
		public int $sequenceNumber,
		public bool $createdByAgent,
		public ?string $archivedAt,
		public string $createdAt,
		public string $updatedAt,
		public array $tagIds,
	) {
	}

	/** @param list<int> $tagIds */
	public static function fromEntity(Lecture $lecture, array $tagIds = []): self
	{
		return new self(
			id: $lecture->id,
			code: $lecture->course->prefix . '-' . $lecture->sequenceNumber,
			courseId: $lecture->course->id,
			courseName: $lecture->course->name,
			status: $lecture->status->value,
			name: $lecture->name,
			description: $lecture->description,
			tuning: $lecture->tuning,
			capo: $lecture->capo,
			targetTempoBpm: $lecture->targetTempoBpm,
			difficulty: $lecture->difficulty?->value,
			position: $lecture->position,
			sequenceNumber: $lecture->sequenceNumber,
			createdByAgent: $lecture->createdByAgent,
			archivedAt: $lecture->archivedAt?->format(DATE_ATOM),
			createdAt: $lecture->createdAt->format(DATE_ATOM),
			updatedAt: $lecture->updatedAt->format(DATE_ATOM),
			tagIds: $tagIds,
		);
	}
}
