<?php

declare(strict_types=1);

namespace Kytario\Mcp\Dto;

final readonly class McpLectureFileListDto
{
	/** @param list<McpLectureFileDto> $files */
	public function __construct(public array $files)
	{
	}
}
