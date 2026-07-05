<?php

declare(strict_types=1);

namespace Kytario\Service\Provider\Dto;

use Kytario\Model\Entity\ProgressEntry;

final readonly class PracticeSummary
{
	/**
	 * @param array<string, int> $entriesPerWeek ISO week ("2026-W23") => number of entries
	 * @param list<array{practicedAt: string, tempoBpm: int}> $bpmTrend chronological BPM readings
	 */
	public function __construct(public int $totalEntries, public int $totalMinutes, public array $entriesPerWeek, public array $bpmTrend,)
	{
	}

	/** @param list<ProgressEntry> $entries chronological (oldest first) */
	public static function fromEntries(array $entries): self
	{
		$totalMinutes = 0;
		$entriesPerWeek = [];
		$bpmTrend = [];

		foreach ($entries as $entry) {
			$totalMinutes += $entry->durationMinutes ?? 0;

			$week = $entry->practicedAt->format('o-\WW');
			$entriesPerWeek[$week] = ($entriesPerWeek[$week] ?? 0) + 1;

			if ($entry->tempoBpm !== null) {
				$bpmTrend[] = ['practicedAt' => $entry->practicedAt->format('Y-m-d'), 'tempoBpm' => $entry->tempoBpm];
			}
		}

		return new self(count($entries), $totalMinutes, $entriesPerWeek, $bpmTrend);
	}
}
