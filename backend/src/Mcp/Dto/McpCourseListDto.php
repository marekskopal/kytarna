<?php

declare(strict_types=1);

namespace Kytario\Mcp\Dto;

final readonly class McpCourseListDto
{
	/** @param list<McpCourseDto> $courses */
	public function __construct(public array $courses)
	{
	}
}
