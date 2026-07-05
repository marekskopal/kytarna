<?php

declare(strict_types=1);

namespace Kytario\Mcp\Dto;

final readonly class McpLectureLinkListDto
{
	/** @param list<McpLectureLinkDto> $links */
	public function __construct(public array $links)
	{
	}
}
