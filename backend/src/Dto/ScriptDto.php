<?php

declare(strict_types=1);

namespace Ukolio\Dto;

use Ukolio\Model\Entity\Script;
use const DATE_ATOM;

final readonly class ScriptDto
{
	public function __construct(
		public int $id,
		public int $workspaceId,
		public int $createdById,
		public string $name,
		public string $source,
		public string $trigger,
		public ?string $triggerConfig,
		public bool $active,
		public ?string $lastRunAt,
		public ?string $lastStatus,
		public int $runCount,
		public string $createdAt,
		public string $updatedAt,
	) {
	}

	public static function fromEntity(Script $script): self
	{
		return self::fromEntityWithStats($script, null, 0);
	}

	public static function fromEntityWithStats(Script $script, ?string $lastStatus, int $runCount): self
	{
		return new self(
			id: $script->id,
			workspaceId: $script->workspace->id,
			createdById: $script->createdBy->id,
			name: $script->name,
			source: $script->source,
			trigger: $script->trigger->value,
			triggerConfig: $script->triggerConfig,
			active: $script->active,
			lastRunAt: $script->lastRunAt?->format(DATE_ATOM),
			lastStatus: $lastStatus,
			runCount: $runCount,
			createdAt: $script->createdAt->format(DATE_ATOM),
			updatedAt: $script->updatedAt->format(DATE_ATOM),
		);
	}
}
