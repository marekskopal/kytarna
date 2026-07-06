<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use DateTimeImmutable;
use Iterator;
use Kytarna\Model\Entity\Course;
use Kytarna\Model\Entity\Enum\DifficultyEnum;
use Kytarna\Model\Entity\Enum\EventTypeEnum;
use Kytarna\Model\Entity\Enum\LearningStatusEnum;
use Kytarna\Model\Entity\Song;
use Kytarna\Model\Entity\User;
use Kytarna\Model\Entity\Workspace;
use Kytarna\Model\Repository\Enum\ArchivedFilterEnum;
use Kytarna\Model\Repository\Enum\LectureOrderByEnum;
use Kytarna\Model\Repository\Enum\OrderDirectionEnum;
use Kytarna\Model\Repository\SongRepository;
use Kytarna\Service\Actor\ActorContextInterface;
use Kytarna\Service\Storage\FileStorageInterface;
use Kytarna\Service\Storage\S3Config;
use Kytarna\Validator\TextFieldValidator;
use RuntimeException;
use const PATHINFO_EXTENSION;

final readonly class SongProvider implements SongProviderInterface
{
	public function __construct(
		private SongRepository $songRepository,
		private EventProviderInterface $eventProvider,
		private CourseSequenceProviderInterface $courseSequenceProvider,
		private SongPositionManager $positionManager,
		private FileStorageInterface $fileStorage,
		private S3Config $s3Config,
		private ActorContextInterface $actorContext,
		private SongTagProviderInterface $songTagProvider,
		private SongFileProviderInterface $songFileProvider,
		private SongWatcherProviderInterface $songWatcherProvider,
		private ProgressStatusProviderInterface $progressStatusProvider,
	) {
	}

	public function getSong(int $songId): ?Song
	{
		return $this->songRepository->findById($songId);
	}

	/** @return Iterator<Song> */
	public function getSongsByCourse(Course $course, bool $includeArchived = true): Iterator
	{
		return $this->songRepository->findByCourse($course->id, $includeArchived);
	}

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
	): Iterator {
		return $this->songRepository->findInWorkspace(
			$workspace->id,
			$limit,
			$offset,
			$orderBy,
			$direction,
			$search,
			$statuses,
			$onlyActive,
			$archived,
		);
	}

	/** @param list<LearningStatusEnum>|null $statuses */
	public function countSongsInWorkspace(
		Workspace $workspace,
		?string $search,
		?array $statuses,
		bool $onlyActive,
		ArchivedFilterEnum $archived = ArchivedFilterEnum::Active,
	): int {
		return $this->songRepository->countInWorkspace($workspace->id, $search, $statuses, $onlyActive, $archived);
	}

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
	): Song {
		$name = TextFieldValidator::validateName($name, 'Song');
		$description = TextFieldValidator::validateDescription($description);

		$sequenceNumber = $course !== null ? $this->courseSequenceProvider->next($course) : null;
		$position = $this->positionManager->nextPositionIn($workspace->id, $course?->id, $status);

		$now = new DateTimeImmutable();
		$song = new Song(
			workspace: $workspace,
			status: $status,
			name: $name,
			position: $position,
			course: $course,
			sequenceNumber: $sequenceNumber,
			description: $description,
			tuning: $tuning,
			capo: $capo,
			targetTempoBpm: $targetTempoBpm,
			difficulty: $difficulty,
			authorName: self::normaliseShort($authorName),
			albumName: self::normaliseShort($albumName),
			createdByAgent: $this->actorContext->isAgent(),
		);
		$song->createdAt = $now;
		$song->updatedAt = $now;

		$this->songRepository->persist($song);

		if ($tagIds !== null) {
			$this->applyTags($author, $song, $tagIds);
		}

		$this->recordSongEvent($author, $song, EventTypeEnum::SongCreated, ['name' => $name]);

		return $song;
	}

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
	): Song {
		$name = TextFieldValidator::validateName($name, 'Song');
		$description = TextFieldValidator::validateDescription($description);

		$statusChanged = $song->status !== $status;

		$song->name = $name;
		$song->description = $description;
		$song->tuning = $tuning;
		$song->capo = $capo;
		$song->targetTempoBpm = $targetTempoBpm;
		$song->difficulty = $difficulty;
		$song->authorName = self::normaliseShort($authorName);
		$song->albumName = self::normaliseShort($albumName);
		if ($statusChanged) {
			$song->status = $status;
			$song->position = $this->positionManager->nextPositionIn($song->workspace->id, $song->course?->id, $status);
		}
		$song->updatedAt = new DateTimeImmutable();
		$this->songRepository->persist($song);

		if ($tagIds !== null) {
			$this->applyTags($author, $song, $tagIds);
		}

		$this->recordSongEvent($author, $song, EventTypeEnum::SongUpdated, ['name' => $name]);

		return $song;
	}

	public function moveSong(User $author, Song $song, LearningStatusEnum $newStatus, int $newPosition): Song
	{
		$fromStatus = $song->status;
		if ($fromStatus === $newStatus) {
			$this->positionManager->reorderWithinColumn($song, $newPosition);
		} else {
			$this->positionManager->closeGapInColumn($song);
			$this->positionManager->openSlot($song->workspace->id, $song->course?->id, $newStatus, $newPosition);
			$song->status = $newStatus;
			$song->position = $newPosition;
		}
		$song->updatedAt = new DateTimeImmutable();
		$this->songRepository->persist($song);

		$this->recordSongEvent($author, $song, EventTypeEnum::SongMoved, [
			'name' => $song->name,
			'fromStatus' => $fromStatus->value,
			'toStatus' => $newStatus->value,
		]);

		return $song;
	}

	public function archiveSong(User $author, Song $song): Song
	{
		if ($song->archivedAt !== null) {
			return $song;
		}
		$song->archivedAt = new DateTimeImmutable();
		$song->updatedAt = new DateTimeImmutable();
		$this->songRepository->persist($song);
		$this->recordSongEvent($author, $song, EventTypeEnum::SongArchived, ['name' => $song->name]);

		return $song;
	}

	public function unarchiveSong(User $author, Song $song): Song
	{
		if ($song->archivedAt === null) {
			return $song;
		}
		$song->archivedAt = null;
		$song->updatedAt = new DateTimeImmutable();
		$this->songRepository->persist($song);
		$this->recordSongEvent($author, $song, EventTypeEnum::SongUnarchived, ['name' => $song->name]);

		return $song;
	}

	public function addToCourse(User $author, Song $song, Course $course): Song
	{
		if ($song->workspace->id !== $course->workspace->id) {
			throw new RuntimeException('Course belongs to a different workspace.');
		}
		if ($song->course !== null && $song->course->id === $course->id) {
			return $song;
		}

		$this->positionManager->closeGapInColumn($song);
		$song->course = $course;
		$song->sequenceNumber = $this->courseSequenceProvider->next($course);
		$song->position = $this->positionManager->nextPositionIn($song->workspace->id, $course->id, $song->status);
		$song->updatedAt = new DateTimeImmutable();
		$this->songRepository->persist($song);

		$this->recordSongEvent($author, $song, EventTypeEnum::SongAddedToCourse, [
			'name' => $song->name,
			'courseName' => $course->name,
		]);

		return $song;
	}

	public function removeFromCourse(User $author, Song $song): Song
	{
		if ($song->course === null) {
			return $song;
		}

		$this->positionManager->closeGapInColumn($song);
		$song->course = null;
		$song->sequenceNumber = null;
		$song->position = $this->positionManager->nextPositionIn($song->workspace->id, null, $song->status);
		$song->updatedAt = new DateTimeImmutable();
		$this->songRepository->persist($song);

		$this->recordSongEvent($author, $song, EventTypeEnum::SongRemovedFromCourse, ['name' => $song->name]);

		return $song;
	}

	public function deleteSong(User $author, Song $song): void
	{
		$this->recordSongEvent($author, $song, EventTypeEnum::SongDeleted, ['name' => $song->name]);

		if ($song->coverImageKey !== null) {
			$this->fileStorage->delete($song->coverImageKey);
		}
		$this->songFileProvider->deleteAllForSong($author, $song);
		$this->songWatcherProvider->deleteAllForSong($song);
		$this->songTagProvider->deleteAllForSong($song);
		$this->progressStatusProvider->deleteAllForSong($song->id);
		$this->songRepository->delete($song);
	}

	/** @param list<int> $tagIds */
	private function applyTags(User $author, Song $song, array $tagIds): void
	{
		$changes = $this->songTagProvider->setTagsForSong($song->workspace, $song, $tagIds);
		if ($changes['added'] === [] && $changes['removed'] === []) {
			return;
		}
		$this->recordSongEvent($author, $song, EventTypeEnum::SongTagsUpdated, [
			'name' => $song->name,
			'added' => $changes['added'],
			'removed' => $changes['removed'],
		]);
	}

	public function setCover(User $author, Song $song, string $filename, string $mimeType, string $body): Song
	{
		$size = strlen($body);
		if ($size === 0) {
			throw new RuntimeException('Cover image is empty.');
		}
		if ($size > $this->s3Config->maxFileSizeBytes) {
			throw new RuntimeException(
				sprintf('Cover image is %d bytes, exceeds the %d-byte limit.', $size, $this->s3Config->maxFileSizeBytes),
			);
		}
		$cleanMimeType = self::sanitiseImageMimeType($mimeType);

		$oldKey = $song->coverImageKey;
		$storageKey = $this->buildCoverKey($song, $filename);
		$this->fileStorage->put($storageKey, $body, $cleanMimeType);

		$song->coverImageKey = $storageKey;
		$song->coverImageMimeType = $cleanMimeType;
		$song->updatedAt = new DateTimeImmutable();
		$this->songRepository->persist($song);

		if ($oldKey !== null && $oldKey !== $storageKey) {
			$this->fileStorage->delete($oldKey);
		}

		return $song;
	}

	public function readCover(Song $song): string
	{
		if ($song->coverImageKey === null) {
			throw new RuntimeException('Song has no cover image.');
		}
		return $this->fileStorage->get($song->coverImageKey);
	}

	public function deleteCover(User $author, Song $song): Song
	{
		if ($song->coverImageKey === null) {
			return $song;
		}
		$this->fileStorage->delete($song->coverImageKey);
		$song->coverImageKey = null;
		$song->coverImageMimeType = null;
		$song->updatedAt = new DateTimeImmutable();
		$this->songRepository->persist($song);

		return $song;
	}

	public function nextPosition(Song $song): int
	{
		return $this->positionManager->nextPosition($song);
	}

	public function nextPositionForStatus(Song $song, LearningStatusEnum $status): int
	{
		return $this->positionManager->nextPositionIn($song->workspace->id, $song->course?->id, $status);
	}

	/** @param array<string, mixed> $metadata */
	private function recordSongEvent(User $author, Song $song, EventTypeEnum $type, array $metadata): void
	{
		$this->eventProvider->recordWorkspaceEvent(
			$author,
			$song->workspace,
			$type,
			array_merge($metadata, [
				'songId' => $song->id,
				'status' => $song->status->value,
				'courseId' => $song->course?->id,
			]),
		);
	}

	private function buildCoverKey(Song $song, string $filename): string
	{
		$uuid = bin2hex(random_bytes(16));
		$ext = pathinfo($filename, PATHINFO_EXTENSION);
		$cleanExt = $ext !== '' ? (preg_replace('/[^A-Za-z0-9]+/', '', $ext) ?? '') : '';
		$suffix = $cleanExt !== '' ? '.' . $cleanExt : '';
		return sprintf('workspaces/%d/songs/%d/cover-%s%s', $song->workspace->id, $song->id, $uuid, $suffix);
	}

	private static function sanitiseImageMimeType(string $mimeType): string
	{
		$trimmed = strtolower(trim($mimeType));
		if (!str_starts_with($trimmed, 'image/') || preg_match('~^image/[a-z0-9.+-]+$~', $trimmed) !== 1) {
			throw new RuntimeException('Cover image must be an image file.');
		}
		return $trimmed;
	}

	private static function normaliseShort(?string $value): ?string
	{
		if ($value === null) {
			return null;
		}
		$trimmed = trim($value);
		if ($trimmed === '') {
			return null;
		}
		return mb_substr($trimmed, 0, 255);
	}
}
