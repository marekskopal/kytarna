<?php

declare(strict_types=1);

namespace Kytario\Mcp\Dto;

use Kytario\Dto\ScriptDto;

final readonly class McpScriptListDto
{
	/** @param list<ScriptDto> $scripts */
	public function __construct(public array $scripts)
	{
	}
}
