<?php

declare(strict_types=1);

namespace Kytarna\Mcp\Dto;

final readonly class McpTagListDto
{
	/** @param list<McpTagDto> $tags */
	public function __construct(public array $tags)
	{
	}
}
