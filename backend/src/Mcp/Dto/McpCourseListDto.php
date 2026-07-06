<?php

declare(strict_types=1);

namespace Kytarna\Mcp\Dto;

final readonly class McpCourseListDto
{
	/** @param list<McpCourseDto> $courses */
	public function __construct(public array $courses)
	{
	}
}
