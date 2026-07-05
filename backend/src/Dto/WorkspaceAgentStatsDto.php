<?php

declare(strict_types=1);

namespace Ukolio\Dto;

final readonly class WorkspaceAgentStatsDto
{
	/** @param list<string> $activeAgentNames */
	public function __construct(
		public int $eventsLast24h,
		public int $tasksCreatedLast24h,
		public int $tasksClosedLast24h,
		public int $activeAgents,
		public array $activeAgentNames,
	) {
	}
}
