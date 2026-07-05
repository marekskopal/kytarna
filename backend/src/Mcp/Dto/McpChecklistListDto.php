<?php

declare(strict_types=1);

namespace Kytario\Mcp\Dto;

final readonly class McpChecklistListDto
{
	/** @param list<McpChecklistItemDto> $items */
	public function __construct(public array $items, public int $total, public int $done,)
	{
	}
}
