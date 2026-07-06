<?php

declare(strict_types=1);

namespace Kytarna\Mcp\Dto;

use Kytarna\Model\Entity\SongFile;
use const DATE_ATOM;

final readonly class McpSongFileDto
{
	public function __construct(
		public int $id,
		public int $songId,
		public string $filename,
		public string $mimeType,
		public int $size,
		public ?int $uploadedByUserId,
		public ?string $uploadedByUserName,
		public bool $uploadedByAgent,
		public string $createdAt,
	) {
	}

	public static function fromEntity(SongFile $file): self
	{
		return new self(
			id: $file->id,
			songId: $file->song->id,
			filename: $file->filename,
			mimeType: $file->mimeType,
			size: $file->size,
			uploadedByUserId: $file->uploadedBy?->id,
			uploadedByUserName: $file->uploadedBy?->name,
			uploadedByAgent: $file->uploadedByAgent,
			createdAt: $file->createdAt->format(DATE_ATOM),
		);
	}
}
