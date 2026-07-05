<?php

declare(strict_types=1);

namespace Kytario\Dto;

use Kytario\Service\Provider\Dto\PracticeSummary;

final readonly class PracticeSummaryDto
{
	/**
	 * @param array<string, int> $entriesPerWeek
	 * @param list<array{practicedAt: string, tempoBpm: int}> $bpmTrend
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
