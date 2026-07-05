<?php

declare(strict_types=1);

namespace Kytario\Mcp\Dto;

final readonly class McpTaskListDto
{
	/** @param list<McpTaskDto> $tasks */
	public function __construct(public array $tasks)
	{
	}
}
