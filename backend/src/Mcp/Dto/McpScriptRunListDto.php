<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Dto;

use Ukolio\Dto\ScriptRunDto;

final readonly class McpScriptRunListDto
{
	/** @param list<ScriptRunDto> $runs */
	public function __construct(public array $runs)
	{
	}
}
