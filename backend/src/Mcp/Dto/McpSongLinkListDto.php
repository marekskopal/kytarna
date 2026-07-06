<?php

declare(strict_types=1);

namespace Kytarna\Mcp\Dto;

final readonly class McpSongLinkListDto
{
	/** @param list<McpSongLinkDto> $links */
	public function __construct(public array $links)
	{
	}
}
