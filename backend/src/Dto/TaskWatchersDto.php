<?php

declare(strict_types=1);

namespace Kytario\Dto;

final readonly class TaskWatchersDto
{
	/** @param list<TaskWatcherDto> $watchers */
	public function __construct(public array $watchers, public bool $watching,)
	{
	}
}
