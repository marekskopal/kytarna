<?php

declare(strict_types=1);

namespace Ukolio\Dto;

final readonly class TaskListDto
{
	/** @param list<TaskListItemDto> $tasks */
	public function __construct(public array $tasks, public int $count,)
	{
	}
}
