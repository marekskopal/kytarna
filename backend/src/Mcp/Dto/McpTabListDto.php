<?php

declare(strict_types=1);

namespace Kytario\Mcp\Dto;

final readonly class McpTabListDto
{
	/** @param list<McpTabDto> $tabs */
	public function __construct(public array $tabs)
	{
	}
}
