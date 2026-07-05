<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Dto;

final readonly class McpFieldListDto
{
	/** @param list<McpFieldDto> $fields */
	public function __construct(public array $fields)
	{
	}
}
