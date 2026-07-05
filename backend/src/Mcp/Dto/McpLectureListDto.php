<?php

declare(strict_types=1);

namespace Kytario\Mcp\Dto;

final readonly class McpLectureListDto
{
	/** @param list<McpLectureDto> $lectures */
	public function __construct(public array $lectures)
	{
	}
}
