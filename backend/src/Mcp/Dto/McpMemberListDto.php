<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Dto;

final readonly class McpMemberListDto
{
	/** @param list<McpMemberDto> $members */
	public function __construct(public array $members)
	{
	}
}
