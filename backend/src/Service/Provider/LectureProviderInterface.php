<?php

declare(strict_types=1);

namespace Kytario\Service\Provider;

use DateTimeImmutable;
use Iterator;
use Kytario\Model\Entity\Course;
use Kytario\Model\Entity\Lecture;
use Kytario\Model\Entity\Status;
use Kytario\Model\Entity\User;
use Kytario\Model\Entity\Workspace;
use Kytario\Model\Repository\Enum\ArchivedFilterEnum;
use Kytario\Model\Repository\Enum\LectureOrderByEnum;
use Kytario\Model\Repository\Enum\OrderDirectionEnum;

interface LectureProviderInterface
{
	public function getLecture(int $lectureId): ?Lecture;

	/** @return Iterator<Lecture> */
	public function getLecturesByCourse(Course $course, bool $includeArchived = true): Iterator;

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
	): Iterator;

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
	): int;

	/** @param list<int>|null $tagIds */
	public function createLecture(
		User $author,
		Course $course,
		Status $status,
		string $name,
		?string $description,
		?array $tagIds = null,
		?DateTimeImmutable $startDate = null,
	): Lecture;

	/** @param list<int>|null $tagIds */
	public function updateLecture(
		User $author,
		Lecture $lecture,
		string $name,
		?string $description,
		Status $status,
		?array $tagIds = null,
		bool $recordEvent = true,
		?DateTimeImmutable $startDate = null,
	): Lecture;

	public function moveLecture(User $author, Lecture $lecture, Status $newStatus, int $newPosition, bool $recordEvent = true): Lecture;

	public function archiveLecture(User $author, Lecture $lecture): Lecture;

	public function unarchiveLecture(User $author, Lecture $lecture): Lecture;

	public function deleteLecture(User $author, Lecture $lecture, bool $recordEvent = true): void;

	public function nextPosition(Status $status): int;
}
