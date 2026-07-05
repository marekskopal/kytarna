<?php

declare(strict_types=1);

namespace Kytario\Dto;

use Kytario\Model\Entity\Status;
use Kytario\Model\Entity\Workflow;

final readonly class WorkflowWithStatusesDto
{
	/** @param list<StatusDto> $statuses */
	public function __construct(
		public int $id,
		public int $projectId,
		public string $projectName,
		public string $name,
		public array $statuses,
	) {
	}

	/** @param iterable<Status> $statuses */
	public static function fromEntity(Workflow $workflow, iterable $statuses): self
	{
		$statusDtos = [];
		foreach ($statuses as $status) {
			$statusDtos[] = StatusDto::fromEntity($status);
		}

		return new self(
			id: $workflow->id,
			projectId: $workflow->project->id,
			projectName: $workflow->project->name,
			name: $workflow->name,
			statuses: $statusDtos,
		);
	}
}
