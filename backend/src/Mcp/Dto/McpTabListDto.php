<?php

declare(strict_types=1);

namespace Kytarna\Mcp\Dto;

final readonly class McpTabListDto
{
	/** @param list<McpTabDto> $tabs */
	public function __construct(public array $tabs)
	{
	}
}
