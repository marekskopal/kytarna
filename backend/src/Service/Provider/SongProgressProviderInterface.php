<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use DateTimeImmutable;
use Kytarna\Model\Entity\Song;
use Kytarna\Model\Entity\SongProgressEntry;
use Kytarna\Model\Entity\User;
use Kytarna\Service\Provider\Dto\PracticeSummary;

interface SongProgressProviderInterface
{
	public function getEntry(int $entryId): ?SongProgressEntry;

	/** @return list<SongProgressEntry> */
	public function getEntriesBySong(Song $song, ?string $from = null, ?string $to = null): array;

	public function createEntry(
		User $author,
		Song $song,
		DateTimeImmutable $practicedAt,
		?string $note = null,
		?int $tempoBpm = null,
		?int $durationMinutes = null,
	): SongProgressEntry;

	public function updateEntry(
		User $author,
		SongProgressEntry $entry,
		DateTimeImmutable $practicedAt,
		?string $note,
		?int $tempoBpm,
		?int $durationMinutes,
	): SongProgressEntry;

	public function deleteEntry(User $author, SongProgressEntry $entry): void;

	public function summarizeSong(Song $song, ?string $from = null, ?string $to = null): PracticeSummary;
}
