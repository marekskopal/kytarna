<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Dto;

final readonly class McpStatusListDto
{
	/** @param list<McpStatusDto> $statuses */
	public function __construct(public array $statuses)
	{
	}
}
