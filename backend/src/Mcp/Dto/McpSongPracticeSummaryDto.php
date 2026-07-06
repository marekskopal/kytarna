<?php

declare(strict_types=1);

namespace Kytarna\Mcp\Dto;

use Kytarna\Service\Provider\Dto\PracticeSummary;

final readonly class McpSongPracticeSummaryDto
{
	/**
	 * @param array<string, int> $entriesPerWeek ISO week ("2026-W23") => number of entries
	 * @param list<array{practicedAt: string, tempoBpm: int}> $bpmTrend chronological BPM readings
	 */
	public function __construct(public int $totalEntries, public int $totalMinutes, public array $entriesPerWeek, public array $bpmTrend,)
	{
	}

	public static function fromSummary(PracticeSummary $summary): self
	{
		return new self(
			totalEntries: $summary->totalEntries,
			totalMinutes: $summary->totalMinutes,
			entriesPerWeek: $summary->entriesPerWeek,
			bpmTrend: $summary->bpmTrend,
		);
	}
}
