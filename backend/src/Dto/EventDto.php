<?php

declare(strict_types=1);

namespace Ukolio\Dto;

use Ukolio\Model\Entity\Event;
use const DATE_ATOM;

final readonly class EventDto
{
	/** @param array<string,mixed> $metadata */
	public function __construct(
		public int $id,
		public ?string $authorName,
		public ?int $taskId,
		public ?string $taskCode,
		public string $type,
		public array $metadata,
		public string $actorType,
		public ?string $mcpClientId,
		public ?string $mcpClientName,
		public string $createdAt,
	) {
	}

	public static function fromEntity(Event $event, ?string $taskCode = null): self
	{
		/** @var array<string,mixed> $metadata */
		$metadata = json_decode($event->metadata, true) ?? [];

		return new self(
			id: $event->id,
			authorName: $event->author?->name,
			taskId: $event->taskId,
			taskCode: $taskCode,
			type: $event->type->value,
			metadata: $metadata,
			actorType: $event->actorType->value,
			mcpClientId: $event->mcpClientId,
			mcpClientName: $event->mcpClientName,
			createdAt: $event->createdAt->format(DATE_ATOM),
		);
	}
}
