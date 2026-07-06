<?php

declare(strict_types=1);

namespace Kytarna\Dto;

use Kytarna\Model\Entity\Workspace;
use const DATE_ATOM;

final readonly class WorkspaceDto
{
	public function __construct(public int $id, public string $name, public int $ownerId, public string $createdAt,)
	{
	}

	public static function fromEntity(Workspace $workspace): self
	{
		return new self(
			id: $workspace->id,
			name: $workspace->name,
			ownerId: $workspace->owner->id,
			createdAt: $workspace->createdAt->format(DATE_ATOM),
		);
	}
}
