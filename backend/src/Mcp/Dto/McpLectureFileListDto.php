<?php

declare(strict_types=1);

namespace Kytarna\Mcp\Dto;

final readonly class McpLectureFileListDto
{
	/** @param list<McpLectureFileDto> $files */
	public function __construct(public array $files)
	{
	}
}
