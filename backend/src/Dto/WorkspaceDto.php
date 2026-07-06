<?php

declare(strict_types=1);

namespace Kytarna\Dto;

use Kytarna\Model\Entity\Workspace;
use const DATE_ATOM;

final readonly class WorkspaceDto
{
	public function __construct(
		public int $id,
		public string $name,
		public int $ownerId,
		public bool $isPublic,
		public ?string $description,
		/** Shareable join code, exposed only to the workspace's Teacher (owner). */
		public ?string $joinCode,
		public string $createdAt,
	) {
	}

	public static function fromEntity(Workspace $workspace, bool $includeJoinCode = false): self
	{
		return new self(
			id: $workspace->id,
			name: $workspace->name,
			ownerId: $workspace->owner->id,
			isPublic: $workspace->isPublic,
			description: $workspace->description,
			joinCode: $includeJoinCode ? $workspace->joinCode : null,
			createdAt: $workspace->createdAt->format(DATE_ATOM),
		);
	}
}
