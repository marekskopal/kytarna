<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Dto;

use Ukolio\Model\Entity\Project;

final readonly class McpProjectDto
{
	public function __construct(public int $id, public string $name, public string $prefix, public ?string $description,)
	{
	}

	public static function fromEntity(Project $project): self
	{
		return new self(id: $project->id, name: $project->name, prefix: $project->prefix, description: $project->description);
	}
}
