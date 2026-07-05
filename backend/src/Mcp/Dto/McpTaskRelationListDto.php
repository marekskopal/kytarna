<?php

declare(strict_types=1);

namespace Kytario\Mcp\Dto;

final readonly class McpTaskRelationListDto
{
	/**
	 * @param list<McpTaskRelationDto> $outgoing
	 * @param list<McpTaskRelationDto> $incoming
	 */
	public function __construct(public array $outgoing, public array $incoming,)
	{
	}
}
