<?php

declare(strict_types=1);

namespace Kytarna\Mcp\Tool;

use Kytarna\Mcp\Dto\McpSongDto;
use Kytarna\Mcp\Dto\McpSongListDto;
use Kytarna\Mcp\McpUserContextInterface;
use Kytarna\Model\Entity\Course;
use Kytarna\Model\Entity\Enum\DifficultyEnum;
use Kytarna\Model\Entity\Enum\LearningStatusEnum;
use Kytarna\Model\Entity\Song;
use Kytarna\Model\Entity\Workspace;
use Kytarna\Model\Repository\Enum\ArchivedFilterEnum;
use Kytarna\Model\Repository\Enum\LectureOrderByEnum;
use Kytarna\Model\Repository\Enum\OrderDirectionEnum;
use Kytarna\Service\Provider\CourseProviderInterface;
use Kytarna\Service\Provider\SongProviderInterface;
use Kytarna\Service\Provider\WorkspaceProviderInterface;
use Mcp\Capability\Attribute\McpTool;
use RuntimeException;

final readonly class SongTools
{
	private const int ListLimit = 200;

	public function __construct(
		private McpUserContextInterface $userContext,
		private SongProviderInterface $songProvider,
		private CourseProviderInterface $courseProvider,
		private WorkspaceProviderInterface $workspaceProvider,
	) {
	}

	/**
	 * List songs in the current workspace (the library). Standalone songs and course-attached songs are both
	 * returned. Optionally filter by status. Archived songs are hidden unless includeArchived=true.
	 *
	 * @param string|null $status Optional: only return songs in this status ("To Learn", "Learning" or "Mastered")
	 * @param string|null $search Optional: case-insensitive name substring
	 * @param bool $includeArchived Include archived songs (default false)
	 */
	#[McpTool(name: 'list_songs', description: 'List songs in the current workspace, optionally filtered by status or name.')]
	public function listSongs(?string $status = null, ?string $search = null, bool $includeArchived = false): McpSongListDto
	{
		$workspace = $this->requireWorkspace();
		$statuses = $status !== null && $status !== '' ? [$this->parseStatus($status)] : null;

		$songs = [];
		foreach ($this->songProvider->getSongsInWorkspace(
			$workspace,
			self::ListLimit,
			0,
			LectureOrderByEnum::CreatedAt,
			OrderDirectionEnum::Desc,
			$search,
			$statuses,
			false,
			$includeArchived ? ArchivedFilterEnum::All : ArchivedFilterEnum::Active,
		) as $song) {
			$songs[] = McpSongDto::fromEntity($song);
		}

		return new McpSongListDto($songs);
	}

	/**
	 * Find a song by case-insensitive name match in the current workspace. Prefers exact over substring matches.
	 *
	 * @param string $name Song name to search for
	 */
	#[McpTool(name: 'find_song_by_name', description: 'Find a song in the workspace by name (case-insensitive).')]
	public function findSongByName(string $name): ?McpSongDto
	{
		$workspace = $this->requireWorkspace();
		$needle = mb_strtolower($name);

		$exact = null;
		$partial = null;
		foreach ($this->songProvider->getSongsInWorkspace(
			$workspace,
			self::ListLimit,
			0,
			LectureOrderByEnum::CreatedAt,
			OrderDirectionEnum::Desc,
			null,
			null,
			false,
			ArchivedFilterEnum::All,
		) as $song) {
			$haystack = mb_strtolower($song->name);
			if ($haystack === $needle) {
				$exact = $song;
				break;
			}
			if ($partial === null && str_contains($haystack, $needle)) {
				$partial = $song;
			}
		}

		$found = $exact ?? $partial;
		return $found !== null ? McpSongDto::fromEntity($found) : null;
	}

	/** @param int $songId Song ID */
	#[McpTool(name: 'get_song', description: 'Get a single song by ID.')]
	public function getSong(int $songId): McpSongDto
	{
		return McpSongDto::fromEntity($this->requireSong($songId));
	}

	/**
	 * Create a workspace song. Defaults to "To Learn" status and no course (standalone library song).
	 * Pass courseId to place it on that course's board.
	 *
	 * @param string $name Song name
	 * @param string|null $status Optional status: "To Learn" (default), "Learning" or "Mastered"
	 * @param string|null $description Optional markdown description
	 * @param string|null $authorName Optional artist / author name
	 * @param string|null $albumName Optional album name
	 * @param string|null $tuning Optional tuning, e.g. "Drop D"
	 * @param int|null $capo Optional capo fret number
	 * @param int|null $targetTempoBpm Optional target practice tempo in BPM
	 * @param string|null $difficulty Optional difficulty: "Beginner", "Intermediate" or "Advanced"
	 * @param int|null $courseId Optional course to attach the song to
	 */
	#[McpTool(name: 'create_song', description: 'Create a workspace song (optionally attached to a course).')]
	public function createSong(
		string $name,
		?string $status = null,
		?string $description = null,
		?string $authorName = null,
		?string $albumName = null,
		?string $tuning = null,
		?int $capo = null,
		?int $targetTempoBpm = null,
		?string $difficulty = null,
		?int $courseId = null,
	): McpSongDto {
		$user = $this->userContext->getUser();
		$workspace = $this->requireWorkspace();
		$course = $courseId !== null ? $this->requireCourse($workspace, $courseId) : null;
		$statusEnum = $status !== null && $status !== '' ? $this->parseStatus($status) : LearningStatusEnum::ToLearn;

		$song = $this->songProvider->createSong(
			author: $user,
			workspace: $workspace,
			name: $name,
			status: $statusEnum,
			description: $description,
			tuning: $tuning,
			capo: $capo,
			targetTempoBpm: $targetTempoBpm,
			difficulty: self::parseDifficulty($difficulty),
			authorName: $authorName,
			albumName: $albumName,
			course: $course,
		);

		return McpSongDto::fromEntity($song);
	}

	/**
	 * Update a song's editable fields. Omitted parameters are left unchanged. Use move_song to change status.
	 *
	 * @param int $songId Song ID
	 * @param string|null $name New name
	 * @param string|null $description New description; empty string clears it
	 * @param string|null $authorName New author; empty string clears it
	 * @param string|null $albumName New album; empty string clears it
	 * @param string|null $tuning New tuning; empty string clears it
	 * @param int|null $capo New capo fret number
	 * @param int|null $targetTempoBpm New target tempo in BPM
	 * @param string|null $difficulty New difficulty ("Beginner"|"Intermediate"|"Advanced"); empty string clears it
	 */
	#[McpTool(name: 'update_song', description: 'Update a song. Use move_song to change its status.')]
	public function updateSong(
		int $songId,
		?string $name = null,
		?string $description = null,
		?string $authorName = null,
		?string $albumName = null,
		?string $tuning = null,
		?int $capo = null,
		?int $targetTempoBpm = null,
		?string $difficulty = null,
	): McpSongDto {
		$user = $this->userContext->getUser();
		$song = $this->requireSong($songId);

		$updated = $this->songProvider->updateSong(
			author: $user,
			song: $song,
			name: $name ?? $song->name,
			description: self::resolveStringField($song->description, $description),
			status: $song->status,
			tuning: self::resolveStringField($song->tuning, $tuning),
			capo: $capo ?? $song->capo,
			targetTempoBpm: $targetTempoBpm ?? $song->targetTempoBpm,
			difficulty: $difficulty === null ? $song->difficulty : self::parseDifficulty($difficulty),
			authorName: self::resolveStringField($song->authorName, $authorName),
			albumName: self::resolveStringField($song->albumName, $albumName),
		);

		return McpSongDto::fromEntity($updated);
	}

	/**
	 * Move a song to a different status. Appends to the end of the destination column.
	 *
	 * @param int $songId Song ID
	 * @param string $status Target status: "To Learn", "Learning" or "Mastered"
	 */
	#[McpTool(name: 'move_song', description: 'Move a song to a different status.')]
	public function moveSong(int $songId, string $status): McpSongDto
	{
		$user = $this->userContext->getUser();
		$song = $this->requireSong($songId);
		$statusEnum = $this->parseStatus($status);
		$position = $this->songProvider->nextPositionForStatus($song, $statusEnum);
		$moved = $this->songProvider->moveSong($user, $song, $statusEnum, $position);

		return McpSongDto::fromEntity($moved);
	}

	/** @param int $songId Song ID */
	#[McpTool(name: 'archive_song', description: 'Archive a song (reversible).')]
	public function archiveSong(int $songId): McpSongDto
	{
		return McpSongDto::fromEntity($this->songProvider->archiveSong($this->userContext->getUser(), $this->requireSong($songId)));
	}

	/** @param int $songId Song ID */
	#[McpTool(name: 'unarchive_song', description: 'Unarchive a song.')]
	public function unarchiveSong(int $songId): McpSongDto
	{
		return McpSongDto::fromEntity($this->songProvider->unarchiveSong($this->userContext->getUser(), $this->requireSong($songId)));
	}

	/**
	 * Attach a song to a course so it appears on that course's board. Assigns a PREFIX-N code.
	 *
	 * @param int $songId Song ID
	 * @param int $courseId Course ID
	 */
	#[McpTool(name: 'add_song_to_course', description: 'Attach a song to a course (shows on the board, gets a code).')]
	public function addSongToCourse(int $songId, int $courseId): McpSongDto
	{
		$user = $this->userContext->getUser();
		$workspace = $this->requireWorkspace();
		$song = $this->requireSong($songId);
		$course = $this->requireCourse($workspace, $courseId);

		return McpSongDto::fromEntity($this->songProvider->addToCourse($user, $song, $course));
	}

	/**
	 * Detach a song from its course, returning it to the standalone library.
	 *
	 * @param int $songId Song ID
	 */
	#[McpTool(name: 'remove_song_from_course', description: 'Detach a song from its course (back to the library).')]
	public function removeSongFromCourse(int $songId): McpSongDto
	{
		return McpSongDto::fromEntity($this->songProvider->removeFromCourse($this->userContext->getUser(), $this->requireSong($songId)));
	}

	/** @param int $songId Song ID */
	#[McpTool(name: 'delete_song', description: 'Delete a song (irreversible).')]
	public function deleteSong(int $songId): string
	{
		$this->songProvider->deleteSong($this->userContext->getUser(), $this->requireSong($songId));
		return 'Song deleted.';
	}

	private function requireWorkspace(): Workspace
	{
		$workspace = $this->workspaceProvider->getCurrentWorkspace($this->userContext->getUser());
		if ($workspace === null) {
			throw new RuntimeException('No active workspace.');
		}
		return $workspace;
	}

	private function requireSong(int $songId): Song
	{
		$workspace = $this->requireWorkspace();
		$song = $this->songProvider->getSong($songId);
		if ($song === null || $song->workspace->id !== $workspace->id) {
			throw new RuntimeException(sprintf('Song %d not found.', $songId));
		}
		return $song;
	}

	private function requireCourse(Workspace $workspace, int $courseId): Course
	{
		$course = $this->courseProvider->getCourse($workspace, $courseId);
		if ($course === null) {
			throw new RuntimeException(sprintf('Course %d not found.', $courseId));
		}
		return $course;
	}

	private function parseStatus(string $raw): LearningStatusEnum
	{
		return LearningStatusEnum::fromLoose($raw)
			?? throw new RuntimeException(sprintf('Invalid status "%s"; expected "To Learn", "Learning" or "Mastered".', $raw));
	}

	/** Partial-update string semantics: null leaves the value unchanged, '' clears it, otherwise sets it. */
	private static function resolveStringField(?string $current, ?string $value): ?string
	{
		if ($value === null) {
			return $current;
		}
		return $value === '' ? null : $value;
	}

	private static function parseDifficulty(?string $raw): ?DifficultyEnum
	{
		if ($raw === null || $raw === '') {
			return null;
		}
		return DifficultyEnum::tryFrom($raw)
			?? throw new RuntimeException('Invalid difficulty; expected Beginner, Intermediate or Advanced.');
	}
}
