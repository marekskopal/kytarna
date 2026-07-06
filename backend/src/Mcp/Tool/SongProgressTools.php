<?php

declare(strict_types=1);

namespace Kytarna\Mcp\Tool;

use DateTimeImmutable;
use Kytarna\Dto\DateInput;
use Kytarna\Mcp\Dto\McpSongPracticeSummaryDto;
use Kytarna\Mcp\Dto\McpSongProgressEntryDto;
use Kytarna\Mcp\Dto\McpSongProgressEntryListDto;
use Kytarna\Mcp\McpUserContextInterface;
use Kytarna\Model\Entity\Song;
use Kytarna\Model\Entity\SongProgressEntry;
use Kytarna\Service\Provider\SongProgressProviderInterface;
use Kytarna\Service\Provider\SongProviderInterface;
use Kytarna\Service\Provider\WorkspaceProviderInterface;
use Mcp\Capability\Attribute\McpTool;
use RuntimeException;

final readonly class SongProgressTools
{
	public function __construct(
		private McpUserContextInterface $userContext,
		private SongProviderInterface $songProvider,
		private SongProgressProviderInterface $songProgressProvider,
		private WorkspaceProviderInterface $workspaceProvider,
	) {
	}

	/**
	 * Record a practice session for a song.
	 *
	 * @param int $songId Song ID
	 * @param string|null $practicedAt Date practised (YYYY-MM-DD); defaults to today
	 * @param string|null $note Optional markdown note about the session
	 * @param int|null $tempoBpm Optional tempo reached, in BPM
	 * @param int|null $durationMinutes Optional session length in minutes
	 */
	#[McpTool(name: 'create_song_progress_entry', description: 'Record a practice session for a song.')]
	public function createSongProgressEntry(
		int $songId,
		?string $practicedAt = null,
		?string $note = null,
		?int $tempoBpm = null,
		?int $durationMinutes = null,
	): McpSongProgressEntryDto {
		$user = $this->userContext->getUser();
		$song = $this->requireSong($songId);

		$entry = $this->songProgressProvider->createEntry(
			$user,
			$song,
			DateInput::parse($practicedAt, 'practicedAt') ?? new DateTimeImmutable('today'),
			$note,
			$tempoBpm,
			$durationMinutes,
		);

		return McpSongProgressEntryDto::fromEntity($entry);
	}

	/**
	 * List practice entries for a song, oldest first. Optionally restrict to a date range.
	 *
	 * @param int $songId Song ID
	 * @param string|null $from Inclusive start date (YYYY-MM-DD)
	 * @param string|null $to Inclusive end date (YYYY-MM-DD)
	 */
	#[McpTool(name: 'list_song_progress_entries', description: 'List a song\'s practice entries, optionally within a date range.')]
	public function listSongProgressEntries(int $songId, ?string $from = null, ?string $to = null): McpSongProgressEntryListDto
	{
		$user = $this->userContext->getUser();
		$song = $this->requireSong($songId);
		$entries = array_map(
			static fn (SongProgressEntry $entry): McpSongProgressEntryDto => McpSongProgressEntryDto::fromEntity($entry),
			$this->songProgressProvider->getEntriesBySong($user, $song, $this->normalizeDate($from), $this->normalizeDate($to)),
		);

		return new McpSongProgressEntryListDto($entries);
	}

	/**
	 * Update a practice entry. Omitted parameters are left unchanged; pass an empty note to clear it.
	 *
	 * @param int $progressEntryId Progress entry ID
	 * @param string|null $practicedAt New date (YYYY-MM-DD)
	 * @param string|null $note New note; empty string clears it
	 * @param int|null $tempoBpm New tempo in BPM
	 * @param int|null $durationMinutes New duration in minutes
	 */
	#[McpTool(name: 'update_song_progress_entry', description: 'Update a practice entry (omitted fields unchanged).')]
	public function updateSongProgressEntry(
		int $progressEntryId,
		?string $practicedAt = null,
		?string $note = null,
		?int $tempoBpm = null,
		?int $durationMinutes = null,
	): McpSongProgressEntryDto {
		$user = $this->userContext->getUser();
		$entry = $this->requireEntry($progressEntryId);

		$updated = $this->songProgressProvider->updateEntry(
			$user,
			$entry,
			DateInput::parse($practicedAt, 'practicedAt') ?? $entry->practicedAt,
			$note === null ? $entry->note : ($note === '' ? null : $note),
			$tempoBpm ?? $entry->tempoBpm,
			$durationMinutes ?? $entry->durationMinutes,
		);

		return McpSongProgressEntryDto::fromEntity($updated);
	}

	/**
	 * Delete a practice entry.
	 *
	 * @param int $progressEntryId Progress entry ID
	 */
	#[McpTool(name: 'delete_song_progress_entry', description: 'Delete a practice entry (irreversible).')]
	public function deleteSongProgressEntry(int $progressEntryId): string
	{
		$user = $this->userContext->getUser();
		$entry = $this->requireEntry($progressEntryId);
		$this->songProgressProvider->deleteEntry($user, $entry);
		return 'Progress entry deleted.';
	}

	/**
	 * Aggregate practice stats for a song: total entries, total minutes, entries per ISO week, and the
	 * BPM trend (chronological {practicedAt, tempoBpm}).
	 *
	 * @param int $songId Song ID
	 * @param string|null $from Inclusive start date (YYYY-MM-DD)
	 * @param string|null $to Inclusive end date (YYYY-MM-DD)
	 */
	#[McpTool(name: 'get_song_practice_summary', description: 'Practice stats (totals, per-week counts, BPM trend) for a song.')]
	public function getSongPracticeSummary(int $songId, ?string $from = null, ?string $to = null): McpSongPracticeSummaryDto
	{
		$summary = $this->songProgressProvider->summarizeSong(
			$this->userContext->getUser(),
			$this->requireSong($songId),
			$this->normalizeDate($from),
			$this->normalizeDate($to),
		);

		return McpSongPracticeSummaryDto::fromSummary($summary);
	}

	private function normalizeDate(?string $value): ?string
	{
		return DateInput::parse($value, 'date')?->format('Y-m-d');
	}

	private function requireSong(int $songId): Song
	{
		$song = $this->songProvider->getSong($songId);
		if ($song === null || !$this->workspaceProvider->isMember($this->userContext->getUser(), $song->workspace)) {
			throw new RuntimeException(sprintf('Song %d not found.', $songId));
		}
		return $song;
	}

	private function requireEntry(int $entryId): SongProgressEntry
	{
		$entry = $this->songProgressProvider->getEntry($entryId);
		if ($entry === null || !$this->workspaceProvider->isMember($this->userContext->getUser(), $entry->song->workspace)) {
			throw new RuntimeException(sprintf('Progress entry %d not found.', $entryId));
		}
		return $entry;
	}
}
