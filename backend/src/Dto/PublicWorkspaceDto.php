<?php

declare(strict_types=1);

namespace Kytarna\Dto;

use Kytarna\Model\Entity\Workspace;

/** A workspace as shown in the public teacher directory (no join code). */
final readonly class PublicWorkspaceDto
{
	public function __construct(
		public int $id,
		public string $name,
		public ?string $description,
		public string $teacherName,
		public int $memberCount,
	) {
	}

	public static function fromEntity(Workspace $workspace, int $memberCount): self
	{
		return new self(
			id: $workspace->id,
			name: $workspace->name,
			description: $workspace->description,
			teacherName: $workspace->owner->name,
			memberCount: $memberCount,
		);
	}
}
