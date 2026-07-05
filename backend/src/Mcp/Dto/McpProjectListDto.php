<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Dto;

final readonly class McpProjectListDto
{
	/** @param list<McpProjectDto> $projects */
	public function __construct(public array $projects)
	{
	}
}
