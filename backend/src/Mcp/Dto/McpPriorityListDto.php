<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Dto;

final readonly class McpPriorityListDto
{
	/** @param list<McpPriorityDto> $priorities */
	public function __construct(public array $priorities)
	{
	}
}
