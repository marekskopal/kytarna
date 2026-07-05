<?php

declare(strict_types=1);

namespace Kytario\Mcp\Dto;

use Kytario\Model\Entity\Lecture;

final readonly class McpLectureDto
{
	/** @param list<int> $tagIds */
	public function __construct(
		public int $id,
		public string $code,
		public int $courseId,
		public int $statusId,
		public string $statusName,
		public string $name,
		public ?string $description,
		public ?string $startDate,
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
			statusId: $lecture->status->id,
			statusName: $lecture->status->name,
			name: $lecture->name,
			description: $lecture->description,
			startDate: $lecture->startDate?->format('Y-m-d'),
			position: $lecture->position,
			sequenceNumber: $lecture->sequenceNumber,
			archived: $lecture->archivedAt !== null,
			archivedAt: $lecture->archivedAt?->format('Y-m-d H:i:s'),
			tagIds: $tagIds,
		);
	}
}
