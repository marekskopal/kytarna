<?php

declare(strict_types=1);

namespace Kytario\Mcp\Dto;

use Kytario\Dto\ScriptRunDto;

final readonly class McpScriptRunListDto
{
	/** @param list<ScriptRunDto> $runs */
	public function __construct(public array $runs)
	{
	}
}
