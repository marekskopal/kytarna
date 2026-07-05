<?php

declare(strict_types=1);

namespace Ukolio\Dto;

use Ukolio\Model\Entity\TaskTemplate;
use const DATE_ATOM;

final readonly class TaskTemplateDto
{
	public function __construct(
		public int $id,
		public int $workspaceId,
		public string $name,
		public TaskTemplatePayloadDto $payload,
		public string $createdAt,
		public string $updatedAt,
	) {
	}

	public static function fromEntity(TaskTemplate $template): self
	{
		return new self(
			id: $template->id,
			workspaceId: $template->workspace->id,
			name: $template->name,
			payload: TaskTemplatePayloadDto::fromJson($template->payload),
			createdAt: $template->createdAt->format(DATE_ATOM),
			updatedAt: $template->updatedAt->format(DATE_ATOM),
		);
	}
}
