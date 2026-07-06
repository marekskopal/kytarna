<?php

declare(strict_types=1);

namespace Kytarna\Mcp\Dto;

final readonly class McpProgressEntryListDto
{
	/** @param list<McpProgressEntryDto> $entries */
	public function __construct(public array $entries)
	{
	}
}
