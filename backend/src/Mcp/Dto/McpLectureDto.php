<?php

declare(strict_types=1);

namespace Kytarna\Mcp\Dto;

use Kytarna\Model\Entity\Lecture;

final readonly class McpLectureDto
{
	/** @param list<int> $tagIds */
	public function __construct(
		public int $id,
		public string $code,
		public int $courseId,
		public string $status,
		public string $statusLabel,
		public string $name,
		public ?string $description,
		public ?string $tuning,
		public ?int $capo,
		public ?int $targetTempoBpm,
		public ?string $difficulty,
		public int $position,
		public int $sequenceNumber,
		public bool $archived,
		public ?string $archivedAt,
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
			status: $lecture->status->value,
			statusLabel: $lecture->status->label(),
			name: $lecture->name,
			description: $lecture->description,
			tuning: $lecture->tuning,
			capo: $lecture->capo,
			targetTempoBpm: $lecture->targetTempoBpm,
			difficulty: $lecture->difficulty?->value,
			position: $lecture->position,
			sequenceNumber: $lecture->sequenceNumber,
			archived: $lecture->archivedAt !== null,
			archivedAt: $lecture->archivedAt?->format('Y-m-d H:i:s'),
			tagIds: $tagIds,
		);
	}
}
