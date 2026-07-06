<?php

declare(strict_types=1);

namespace Kytarna\Mcp\Dto;

final readonly class McpLectureListDto
{
	/** @param list<McpLectureDto> $lectures */
	public function __construct(public array $lectures)
	{
	}
}
