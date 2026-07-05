<?php

declare(strict_types=1);

namespace Kytario\Dto;

use Kytario\Model\Entity\Event;
use const DATE_ATOM;

final readonly class EventDto
{
	/** @param array<string,mixed> $metadata */
	public function __construct(
		public int $id,
		public ?string $authorName,
		public ?int $lectureId,
		public ?string $lectureCode,
		public string $type,
		public array $metadata,
		public string $actorType,
		public ?string $mcpClientId,
		public ?string $mcpClientName,
		public string $createdAt,
	) {
	}

	public static function fromEntity(Event $event, ?string $lectureCode = null): self
	{
		/** @var array<string,mixed> $metadata */
		$metadata = json_decode($event->metadata, true) ?? [];

		return new self(
			id: $event->id,
			authorName: $event->author?->name,
			lectureId: $event->lectureId,
			lectureCode: $lectureCode,
			type: $event->type->value,
			metadata: $metadata,
			actorType: $event->actorType->value,
			mcpClientId: $event->mcpClientId,
			mcpClientName: $event->mcpClientName,
			createdAt: $event->createdAt->format(DATE_ATOM),
		);
	}
}
