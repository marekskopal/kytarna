<?php

declare(strict_types=1);

namespace Kytario\Mcp\Dto;

final readonly class McpTagListDto
{
	/** @param list<McpTagDto> $tags */
	public function __construct(public array $tags)
	{
	}
}
