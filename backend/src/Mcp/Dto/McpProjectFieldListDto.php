<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Dto;

final readonly class McpProjectFieldListDto
{
	/** @param list<McpProjectFieldDto> $projectFields */
	public function __construct(public array $projectFields)
	{
	}
}
