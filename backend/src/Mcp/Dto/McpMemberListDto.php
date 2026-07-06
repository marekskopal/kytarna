<?php

declare(strict_types=1);

namespace Kytarna\Mcp\Dto;

final readonly class McpMemberListDto
{
	/** @param list<McpMemberDto> $members */
	public function __construct(public array $members)
	{
	}
}
