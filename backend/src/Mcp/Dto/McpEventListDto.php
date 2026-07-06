<?php

declare(strict_types=1);

namespace Kytarna\Mcp\Dto;

final readonly class McpEventListDto
{
	/** @param list<McpEventDto> $events */
	public function __construct(public array $events)
	{
	}
}
