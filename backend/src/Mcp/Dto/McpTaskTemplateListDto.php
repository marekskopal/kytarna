<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Dto;

final readonly class McpTaskTemplateListDto
{
	/** @param list<McpTaskTemplateDto> $templates */
	public function __construct(public array $templates)
	{
	}
}
