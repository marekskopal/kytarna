<?php

declare(strict_types=1);

namespace Kytario\Mcp\Dto;

final readonly class McpTaskCommentListDto
{
	/** @param list<McpTaskCommentDto> $comments */
	public function __construct(public array $comments)
	{
	}
}
