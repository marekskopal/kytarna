<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use DateTimeImmutable;
use Kytarna\Model\Entity\Song;
use Kytarna\Model\Entity\SongProgressEntry;
use Kytarna\Model\Entity\User;
use Kytarna\Model\Repository\SongProgressEntryRepository;
use Kytarna\Service\Provider\Dto\PracticeSummary;

final readonly class SongProgressProvider implements SongProgressProviderInterface
{
	public function __construct(private SongProgressEntryRepository $songProgressEntryRepository)
	{
	}

	public function getEntry(int $entryId): ?SongProgressEntry
	{
		return $this->songProgressEntryRepository->findById($entryId);
	}

	/** @return list<SongProgressEntry> */
	public function getEntriesBySong(Song $song, ?string $from = null, ?string $to = null): array
	{
		$result = [];
		foreach ($this->songProgressEntryRepository->findBySong($song->id, $from, $to) as $entry) {
			$result[] = $entry;
		}
		return $result;
	}

	public function createEntry(
		User $author,
		Song $song,
		DateTimeImmutable $practicedAt,
		?string $note = null,
		?int $tempoBpm = null,
		?int $durationMinutes = null,
	): SongProgressEntry {
		$now = new DateTimeImmutable();
		$entry = new SongProgressEntry(
			song: $song,
			practicedAt: $practicedAt,
			note: $note,
			tempoBpm: $tempoBpm,
			durationMinutes: $durationMinutes,
		);
		$entry->createdAt = $now;
		$entry->updatedAt = $now;

		$this->songProgressEntryRepository->persist($entry);

		return $entry;
	}

	public function updateEntry(
		User $author,
		SongProgressEntry $entry,
		DateTimeImmutable $practicedAt,
		?string $note,
		?int $tempoBpm,
		?int $durationMinutes,
	): SongProgressEntry {
		$entry->practicedAt = $practicedAt;
		$entry->note = $note;
		$entry->tempoBpm = $tempoBpm;
		$entry->durationMinutes = $durationMinutes;
		$entry->updatedAt = new DateTimeImmutable();

		$this->songProgressEntryRepository->persist($entry);

		return $entry;
	}

	public function deleteEntry(User $author, SongProgressEntry $entry): void
	{
		$this->songProgressEntryRepository->delete($entry);
	}

	public function summarizeSong(Song $song, ?string $from = null, ?string $to = null): PracticeSummary
	{
		return PracticeSummary::fromEntries($this->getEntriesBySong($song, $from, $to));
	}
}
