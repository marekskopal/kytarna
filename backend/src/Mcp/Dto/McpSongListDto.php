<?php

declare(strict_types=1);

namespace Kytarna\Mcp\Dto;

final readonly class McpSongListDto
{
	/** @param list<McpSongDto> $songs */
	public function __construct(public array $songs)
	{
	}
}
