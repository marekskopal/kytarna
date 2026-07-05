<?php

declare(strict_types=1);

namespace Ukolio\Dto;

use Ukolio\Model\Entity\Workflow;

final readonly class WorkflowDto
{
	public function __construct(public int $id, public int $projectId, public string $name)
	{
	}

	public static function fromEntity(Workflow $workflow): self
	{
		return new self(id: $workflow->id, projectId: $workflow->project->id, name: $workflow->name);
	}
}
