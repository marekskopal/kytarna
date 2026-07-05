<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Dto;

final readonly class McpTaskFileListDto
{
	/** @param list<McpTaskFileDto> $files */
	public function __construct(public array $files)
	{
	}
}
