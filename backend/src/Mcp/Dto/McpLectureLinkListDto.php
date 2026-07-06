<?php

declare(strict_types=1);

namespace Kytarna\Mcp\Dto;

final readonly class McpLectureLinkListDto
{
	/** @param list<McpLectureLinkDto> $links */
	public function __construct(public array $links)
	{
	}
}
