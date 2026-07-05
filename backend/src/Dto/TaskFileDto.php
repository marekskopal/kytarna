<?php

declare(strict_types=1);

namespace Kytario\Dto;

use Kytario\Model\Entity\TaskFile;
use const DATE_ATOM;

final readonly class TaskFileDto
{
	public function __construct(
		public int $id,
		public int $taskId,
		public string $filename,
		public string $mimeType,
		public int $size,
		public ?int $uploadedByUserId,
		public ?string $uploadedByUserName,
		public bool $uploadedByAgent,
		public string $createdAt,
	) {
	}

	public static function fromEntity(TaskFile $file): self
	{
		return new self(
			id: $file->id,
			taskId: $file->task->id,
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
