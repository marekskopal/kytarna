<?php

declare(strict_types=1);

namespace Kytarna\Mcp\Dto;

use Kytarna\Model\Entity\Tag;

final readonly class McpTagDto
{
	public function __construct(public int $id, public int $workspaceId, public string $name, public string $color,)
	{
	}

	public static function fromEntity(Tag $tag): self
	{
		return new self(id: $tag->id, workspaceId: $tag->workspace->id, name: $tag->name, color: $tag->color);
	}
}
