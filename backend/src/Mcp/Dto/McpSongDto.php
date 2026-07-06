<?php

declare(strict_types=1);

namespace Kytarna\Mcp\Dto;

use Kytarna\Model\Entity\Song;

final readonly class McpSongDto
{
	public function __construct(
		public int $id,
		public ?string $code,
		public ?int $courseId,
		public ?string $courseName,
		public string $status,
		public string $statusLabel,
		public string $name,
		public ?string $description,
		public ?string $tuning,
		public ?int $capo,
		public ?int $targetTempoBpm,
		public ?string $difficulty,
		public ?string $authorName,
		public ?string $albumName,
		public bool $hasCover,
		public bool $archived,
		public ?string $archivedAt,
	) {
	}

	public static function fromEntity(Song $song): self
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
			statusLabel: $song->status->label(),
			name: $song->name,
			description: $song->description,
			tuning: $song->tuning,
			capo: $song->capo,
			targetTempoBpm: $song->targetTempoBpm,
			difficulty: $song->difficulty?->value,
			authorName: $song->authorName,
			albumName: $song->albumName,
			hasCover: $song->coverImageKey !== null,
			archived: $song->archivedAt !== null,
			archivedAt: $song->archivedAt?->format('Y-m-d H:i:s'),
		);
	}
}
