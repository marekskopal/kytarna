<?php

declare(strict_types=1);

namespace Kytario\Mcp\Dto;

final readonly class McpTaskFieldValueDto
{
	public function __construct(public int $fieldId, public ?string $value,)
	{
	}
}
