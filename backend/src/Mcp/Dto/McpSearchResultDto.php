<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Dto;

final readonly class McpSearchResultDto
{
	/** @param list<McpSearchHitDto> $hits */
	public function __construct(public array $hits, public int $totalHits,)
	{
	}
}
