<?php

declare(strict_types=1);

namespace Kytario\Dto;

final readonly class TaskListDto
{
	/** @param list<TaskListItemDto> $tasks */
	public function __construct(public array $tasks, public int $count,)
	{
	}
}
