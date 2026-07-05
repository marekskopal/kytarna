<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Dto;

use Ukolio\Dto\ScriptDto;

final readonly class McpScriptListDto
{
	/** @param list<ScriptDto> $scripts */
	public function __construct(public array $scripts)
	{
	}
}
