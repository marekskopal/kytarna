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
		public int $courseId,
		public string $courseName,
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
			courseId: $workflow->course->id,
			courseName: $workflow->course->name,
			name: $workflow->name,
			statuses: $statusDtos,
		);
	}
}
