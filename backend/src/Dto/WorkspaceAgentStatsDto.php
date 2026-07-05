<?php

declare(strict_types=1);

namespace Kytario\Dto;

final readonly class WorkspaceAgentStatsDto
{
	/** @param list<string> $activeAgentNames */
	public function __construct(
		public int $eventsLast24h,
		public int $lecturesCreatedLast24h,
		public int $lecturesClosedLast24h,
		public int $activeAgents,
		public array $activeAgentNames,
	) {
	}
}
