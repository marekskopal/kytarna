<?php

declare(strict_types=1);

namespace Kytarna\Mcp\Dto;

use Kytarna\Model\Entity\Event;
use const JSON_THROW_ON_ERROR;

final readonly class McpEventDto
{
	public function __construct(
		public int $id,
		public string $type,
		public ?int $courseId,
		public ?int $workspaceId,
		public ?int $lectureId,
		public string $actorType,
		public ?int $authorId,
		public ?string $authorName,
		public mixed $metadata,
		public string $createdAt,
	) {
	}

	public static function fromEntity(Event $event): self
	{
		/** @var array<string, mixed> $metadata */
		$metadata = $event->metadata !== ''
			? json_decode($event->metadata, true, 512, JSON_THROW_ON_ERROR)
			: [];

		return new self(
			id: $event->id,
			type: $event->type->value,
			courseId: $event->course?->id,
			workspaceId: $event->workspaceId,
			lectureId: $event->lectureId,
			actorType: $event->actorType->value,
			authorId: $event->author?->id,
			authorName: $event->author?->name,
			metadata: $metadata,
			createdAt: $event->createdAt->format('Y-m-d\TH:i:sP'),
		);
	}
}
