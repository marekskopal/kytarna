<?php

declare(strict_types=1);

namespace Kytario\Dto;

use Kytario\Model\Entity\Workflow;

final readonly class WorkflowDto
{
	public function __construct(public int $id, public int $courseId, public string $name)
	{
	}

	public static function fromEntity(Workflow $workflow): self
	{
		return new self(id: $workflow->id, courseId: $workflow->course->id, name: $workflow->name);
	}
}
