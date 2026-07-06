<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use Iterator;
use Kytarna\Model\Entity\Course;
use Kytarna\Model\Entity\Enum\DifficultyEnum;
use Kytarna\Model\Entity\Enum\LearningStatusEnum;
use Kytarna\Model\Entity\Song;
use Kytarna\Model\Entity\User;
use Kytarna\Model\Entity\Workspace;
use Kytarna\Model\Repository\Enum\ArchivedFilterEnum;
use Kytarna\Model\Repository\Enum\LectureOrderByEnum;
use Kytarna\Model\Repository\Enum\OrderDirectionEnum;

interface SongProviderInterface
{
	public function getSong(int $songId): ?Song;

	/** @return Iterator<Song> */
	public function getSongsByCourse(Course $course, bool $includeArchived = true): Iterator;

	/**
	 * @param list<LearningStatusEnum>|null $statuses
	 * @return Iterator<Song>
	 */
	public function getSongsInWorkspace(
		Workspace $workspace,
		int $limit,
		int $offset,
		LectureOrderByEnum $orderBy,
		OrderDirectionEnum $direction,
		?string $search,
		?array $statuses,
		bool $onlyActive,
		ArchivedFilterEnum $archived = ArchivedFilterEnum::Active,
	): Iterator;

	/** @param list<LearningStatusEnum>|null $statuses */
	public function countSongsInWorkspace(
		Workspace $workspace,
		?string $search,
		?array $statuses,
		bool $onlyActive,
		ArchivedFilterEnum $archived = ArchivedFilterEnum::Active,
	): int;

	/** @param list<int>|null $tagIds */
	public function createSong(
		User $author,
		Workspace $workspace,
		string $name,
		LearningStatusEnum $status,
		?string $description = null,
		?string $tuning = null,
		?int $capo = null,
		?int $targetTempoBpm = null,
		?DifficultyEnum $difficulty = null,
		?string $authorName = null,
		?string $albumName = null,
		?Course $course = null,
		?array $tagIds = null,
	): Song;

	/** @param list<int>|null $tagIds */
	public function updateSong(
		User $author,
		Song $song,
		string $name,
		?string $description,
		LearningStatusEnum $status,
		?string $tuning = null,
		?int $capo = null,
		?int $targetTempoBpm = null,
		?DifficultyEnum $difficulty = null,
		?string $authorName = null,
		?string $albumName = null,
		?array $tagIds = null,
	): Song;

	public function moveSong(User $author, Song $song, LearningStatusEnum $newStatus, int $newPosition): Song;

	public function archiveSong(User $author, Song $song): Song;

	public function unarchiveSong(User $author, Song $song): Song;

	public function addToCourse(User $author, Song $song, Course $course): Song;

	public function removeFromCourse(User $author, Song $song): Song;

	public function deleteSong(User $author, Song $song): void;

	public function setCover(User $author, Song $song, string $filename, string $mimeType, string $body): Song;

	public function readCover(Song $song): string;

	public function deleteCover(User $author, Song $song): Song;

	public function nextPosition(Song $song): int;

	/** End-of-column position for the song's (workspace, course) context in the given status. */
	public function nextPositionForStatus(Song $song, LearningStatusEnum $status): int;
}
