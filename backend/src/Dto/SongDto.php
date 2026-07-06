<?php

declare(strict_types=1);

namespace Kytarna\Dto;

use Kytarna\Model\Entity\Song;
use const DATE_ATOM;

final readonly class SongDto
{
	/** @param list<int> $tagIds */
	public function __construct(
		public int $id,
		public ?string $code,
		public ?int $courseId,
		public ?string $courseName,
		public string $status,
		public string $name,
		public ?string $description,
		public ?string $tuning,
		public ?int $capo,
		public ?int $targetTempoBpm,
		public ?string $difficulty,
		public ?string $authorName,
		public ?string $albumName,
		public bool $hasCover,
		public int $position,
		public ?int $sequenceNumber,
		public bool $createdByAgent,
		public ?string $archivedAt,
		public string $createdAt,
		public string $updatedAt,
		public array $tagIds,
	) {
	}

	/** @param list<int> $tagIds */
	public static function fromEntity(Song $song, array $tagIds = []): self
	{
		$code = $song->course !== null && $song->sequenceNumber !== null
			? $song->course->prefix . '-' . $song->sequenceNumber
			: null;

		return new self(
			id: $song->id,
			code: $code,
			courseId: $song->course?->id,
			courseName: $song->course?->name,
			status: $song->status->value,
			name: $song->name,
			description: $song->description,
			tuning: $song->tuning,
			capo: $song->capo,
			targetTempoBpm: $song->targetTempoBpm,
			difficulty: $song->difficulty?->value,
			authorName: $song->authorName,
			albumName: $song->albumName,
			hasCover: $song->coverImageKey !== null,
			position: $song->position,
			sequenceNumber: $song->sequenceNumber,
			createdByAgent: $song->createdByAgent,
			archivedAt: $song->archivedAt?->format(DATE_ATOM),
			createdAt: $song->createdAt->format(DATE_ATOM),
			updatedAt: $song->updatedAt->format(DATE_ATOM),
			tagIds: $tagIds,
		);
	}
}
