<?php

declare(strict_types=1);

namespace Kytarna\Mcp\Dto;

final readonly class McpSongFileListDto
{
	/** @param list<McpSongFileDto> $files */
	public function __construct(public array $files)
	{
	}
}
