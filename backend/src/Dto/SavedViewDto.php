<?php

declare(strict_types=1);

namespace Ukolio\Dto;

use Ukolio\Model\Entity\SavedView;
use const DATE_ATOM;

final readonly class SavedViewDto
{
	public function __construct(
		public int $id,
		public int $workspaceId,
		public int $userId,
		public string $name,
		public string $filterConfig,
		public string $createdAt,
		public string $updatedAt,
	) {
	}

	public static function fromEntity(SavedView $view): self
	{
		return new self(
			id: $view->id,
			workspaceId: $view->workspace->id,
			userId: $view->user->id,
			name: $view->name,
			filterConfig: $view->filterConfig,
			createdAt: $view->createdAt->format(DATE_ATOM),
			updatedAt: $view->updatedAt->format(DATE_ATOM),
		);
	}
}
