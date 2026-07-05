<?php

declare(strict_types=1);

namespace Ukolio\Dto;

final readonly class BoardDto
{
	/**
	 * @param list<StatusDto> $statuses
	 * @param list<TaskDto> $tasks
	 */
	public function __construct(public ProjectDto $project, public WorkflowDto $workflow, public array $statuses, public array $tasks,)
	{
	}
}
