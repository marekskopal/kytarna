<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use DateTimeImmutable;
use Iterator;
use Kytarna\Model\Entity\Course;
use Kytarna\Model\Entity\Enum\DifficultyEnum;
use Kytarna\Model\Entity\Enum\EventTypeEnum;
use Kytarna\Model\Entity\Lecture;
use Kytarna\Model\Entity\Status;
use Kytarna\Model\Entity\User;
use Kytarna\Model\Entity\Workspace;
use Kytarna\Model\Repository\Enum\ArchivedFilterEnum;
use Kytarna\Model\Repository\Enum\LectureOrderByEnum;
use Kytarna\Model\Repository\Enum\OrderDirectionEnum;
use Kytarna\Model\Repository\LectureRepository;
use Kytarna\Model\Repository\LectureTagRepository;
use Kytarna\Service\Actor\ActorContextInterface;
use Kytarna\Validator\TextFieldValidator;

final readonly class LectureProvider implements LectureProviderInterface
{
	public function __construct(
		private LectureRepository $lectureRepository,
		private EventProviderInterface $eventProvider,
		private LectureFileProviderInterface $lectureFileProvider,
		private LectureWatcherProviderInterface $lectureWatcherProvider,
		private LectureTagProviderInterface $lectureTagProvider,
		private LectureTagRepository $lectureTagRepository,
		private ActorContextInterface $actorContext,
		private LecturePositionManager $positionManager,
	) {
	}

	public function getLecture(int $lectureId): ?Lecture
	{
		return $this->lectureRepository->findById($lectureId);
	}

	/** @return Iterator<Lecture> */
	public function getLecturesByCourse(Course $course, bool $includeArchived = true): Iterator
	{
		return $this->lectureRepository->findByCourse($course->id, $includeArchived);
	}

	/**
	 * @param list<int>|null $statusIds
	 * @param list<int>|null $tagIds
	 * @return Iterator<Lecture>
	 */
	public function getLecturesInWorkspace(
		Workspace $workspace,
		int $limit,
		int $offset,
		LectureOrderByEnum $orderBy,
		OrderDirectionEnum $direction,
		?string $search,
		?array $statusIds,
		bool $onlyActive,
		?array $tagIds = null,
		ArchivedFilterEnum $archived = ArchivedFilterEnum::Active,
	): Iterator {
		return $this->lectureRepository->findInWorkspace(
			$workspace->id,
			$limit,
			$offset,
			$orderBy,
			$direction,
			$search,
			$statusIds,
			$onlyActive,
			$this->resolveLectureIdsByTags($tagIds),
			null,
			$archived,
		);
	}

	/**
	 * @param list<int>|null $statusIds
	 * @param list<int>|null $tagIds
	 */
	public function countLecturesInWorkspace(
		Workspace $workspace,
		?string $search,
		?array $statusIds,
		bool $onlyActive,
		?array $tagIds = null,
		ArchivedFilterEnum $archived = ArchivedFilterEnum::Active,
	): int {
		return $this->lectureRepository->countInWorkspace(
			$workspace->id,
			$search,
			$statusIds,
			$onlyActive,
			$this->resolveLectureIdsByTags($tagIds),
			null,
			$archived,
		);
	}

	/**
	 * @param list<int>|null $tagIds
	 * @return list<int>|null null = no tag filter; [] = no matches
	 */
	private function resolveLectureIdsByTags(?array $tagIds): ?array
	{
		if ($tagIds === null || $tagIds === []) {
			return null;
		}
		return $this->lectureTagRepository->findLectureIdsByTagIds($tagIds);
	}

	/** @param list<int>|null $tagIds */
	public function createLecture(
		User $author,
		Course $course,
		Status $status,
		string $name,
		?string $description,
		?array $tagIds = null,
		?string $tuning = null,
		?int $capo = null,
		?int $targetTempoBpm = null,
		?DifficultyEnum $difficulty = null,
	): Lecture {
		$name = TextFieldValidator::validateName($name, 'Lecture');
		$description = TextFieldValidator::validateDescription($description);

		$position = $this->nextPosition($status);
		$sequenceNumber = $this->lectureRepository->nextSequenceNumber($course->id);

		$now = new DateTimeImmutable();
		$lecture = new Lecture(
			course: $course,
			status: $status,
			name: $name,
			description: $description,
			position: $position,
			sequenceNumber: $sequenceNumber,
			tuning: $tuning,
			capo: $capo,
			targetTempoBpm: $targetTempoBpm,
			difficulty: $difficulty,
			createdByAgent: $this->actorContext->isAgent(),
		);
		$lecture->createdAt = $now;
		$lecture->updatedAt = $now;

		$this->lectureRepository->persist($lecture);

		if ($tagIds !== null) {
			$tagChanges = $this->lectureTagProvider->setTagsForLecture($course->workspace, $lecture, $tagIds);
			if ($tagChanges['added'] !== [] || $tagChanges['removed'] !== []) {
				$this->eventProvider->recordEvent(
					$author,
					$course,
					EventTypeEnum::LectureTagsUpdated,
					['lectureName' => $lecture->name, 'added' => $tagChanges['added'], 'removed' => $tagChanges['removed']],
					$lecture->id,
				);
			}
		}

		$this->eventProvider->recordEvent(
			$author,
			$course,
			EventTypeEnum::LectureCreated,
			['name' => $name, 'statusId' => $status->id, 'statusName' => $status->name],
			$lecture->id,
		);

		return $lecture;
	}

	/** @param list<int>|null $tagIds */
	public function updateLecture(
		User $author,
		Lecture $lecture,
		string $name,
		?string $description,
		Status $status,
		?array $tagIds = null,
		bool $recordEvent = true,
		?string $tuning = null,
		?int $capo = null,
		?int $targetTempoBpm = null,
		?DifficultyEnum $difficulty = null,
	): Lecture {
		$name = TextFieldValidator::validateName($name, 'Lecture');
		$description = TextFieldValidator::validateDescription($description);

		$oldName = $lecture->name;
		$statusChanged = $lecture->status->id !== $status->id;

		$lecture->name = $name;
		$lecture->description = $description;
		$lecture->tuning = $tuning;
		$lecture->capo = $capo;
		$lecture->targetTempoBpm = $targetTempoBpm;
		$lecture->difficulty = $difficulty;
		if ($statusChanged) {
			$lecture->status = $status;
			$lecture->position = $this->positionManager->nextPosition($status);
		}
		$lecture->updatedAt = new DateTimeImmutable();
		$this->lectureRepository->persist($lecture);

		$tagChanges = $tagIds !== null
			? $this->lectureTagProvider->setTagsForLecture($lecture->course->workspace, $lecture, $tagIds)
			: ['added' => [], 'removed' => []];

		if ($recordEvent) {
			$this->recordUpdateEvents($author, $lecture, $name, $oldName, $tagChanges);
		}

		return $lecture;
	}

	public function moveLecture(User $author, Lecture $lecture, Status $newStatus, int $newPosition, bool $recordEvent = true): Lecture
	{
		$fromStatus = $lecture->status;
		$fromPosition = $lecture->position;
		$sameColumn = $fromStatus->id === $newStatus->id;

		if ($sameColumn) {
			$this->positionManager->reorderWithinColumn($lecture, $newPosition);
		} else {
			$this->positionManager->closeGapInOldColumn($lecture);
			$this->positionManager->openSlotInNewColumn($newStatus, $newPosition);
			$lecture->status = $newStatus;
			$lecture->position = $newPosition;
		}
		$lecture->updatedAt = new DateTimeImmutable();
		$this->lectureRepository->persist($lecture);

		if ($recordEvent) {
			$this->recordMoveEvent($author, $lecture, $fromStatus, $newStatus, $fromPosition, $newPosition);
		}

		return $lecture;
	}

	/** @param array{added: list<int>, removed: list<int>} $tagChanges */
	private function recordUpdateEvents(User $author, Lecture $lecture, string $name, string $oldName, array $tagChanges): void
	{
		$this->eventProvider->recordEvent(
			$author,
			$lecture->course,
			EventTypeEnum::LectureUpdated,
			['name' => $name, 'oldName' => $oldName],
			$lecture->id,
		);

		if ($tagChanges['added'] === [] && $tagChanges['removed'] === []) {
			return;
		}
		$this->eventProvider->recordEvent(
			$author,
			$lecture->course,
			EventTypeEnum::LectureTagsUpdated,
			['lectureName' => $lecture->name, 'added' => $tagChanges['added'], 'removed' => $tagChanges['removed']],
			$lecture->id,
		);
	}

	private function recordMoveEvent(
		User $author,
		Lecture $lecture,
		Status $fromStatus,
		Status $newStatus,
		int $fromPosition,
		int $newPosition,
	): void
	{
		$this->eventProvider->recordEvent(
			$author,
			$lecture->course,
			EventTypeEnum::LectureMoved,
			[
				'fromStatusId' => $fromStatus->id,
				'fromStatusName' => $fromStatus->name,
				'toStatusId' => $newStatus->id,
				'toStatusName' => $newStatus->name,
				'fromPosition' => $fromPosition,
				'toPosition' => $newPosition,
				'lectureName' => $lecture->name,
			],
			$lecture->id,
		);
	}

	public function archiveLecture(User $author, Lecture $lecture): Lecture
	{
		if ($lecture->archivedAt !== null) {
			return $lecture;
		}

		$lecture->archivedAt = new DateTimeImmutable();
		$lecture->updatedAt = new DateTimeImmutable();
		$this->lectureRepository->persist($lecture);

		$this->eventProvider->recordEvent(
			$author,
			$lecture->course,
			EventTypeEnum::LectureArchived,
			['name' => $lecture->name, 'statusId' => $lecture->status->id, 'statusName' => $lecture->status->name],
			$lecture->id,
		);

		return $lecture;
	}

	public function unarchiveLecture(User $author, Lecture $lecture): Lecture
	{
		if ($lecture->archivedAt === null) {
			return $lecture;
		}

		$lecture->archivedAt = null;
		$lecture->updatedAt = new DateTimeImmutable();
		$this->lectureRepository->persist($lecture);

		$this->eventProvider->recordEvent(
			$author,
			$lecture->course,
			EventTypeEnum::LectureUnarchived,
			['name' => $lecture->name, 'statusId' => $lecture->status->id, 'statusName' => $lecture->status->name],
			$lecture->id,
		);

		return $lecture;
	}

	public function deleteLecture(User $author, Lecture $lecture, bool $recordEvent = true): void
	{
		if ($recordEvent) {
			$this->eventProvider->recordEvent(
				$author,
				$lecture->course,
				EventTypeEnum::LectureDeleted,
				['name' => $lecture->name],
				$lecture->id,
			);
		}

		$this->lectureFileProvider->deleteAllForLecture($author, $lecture);
		$this->lectureWatcherProvider->deleteAllForLecture($lecture);
		$this->lectureTagProvider->deleteAllForLecture($lecture);
		$this->lectureRepository->delete($lecture);
	}

	public function nextPosition(Status $status): int
	{
		return $this->positionManager->nextPosition($status);
	}
}
