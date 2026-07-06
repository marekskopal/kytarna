<?php

declare(strict_types=1);

namespace Kytarna\Mcp\Dto;

final readonly class McpSongProgressEntryListDto
{
	/** @param list<McpSongProgressEntryDto> $entries */
	public function __construct(public array $entries)
	{
	}
}
