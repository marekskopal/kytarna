<?php

declare(strict_types=1);

namespace Kytarna\Mcp\Dto;

final readonly class McpSongTabListDto
{
	/** @param list<McpSongTabDto> $tabs */
	public function __construct(public array $tabs)
	{
	}
}
