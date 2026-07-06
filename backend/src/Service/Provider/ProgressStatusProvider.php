<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use DateTimeImmutable;
use Kytarna\Model\Entity\Course;
use Kytarna\Model\Entity\Enum\LearningStatusEnum;
use Kytarna\Model\Entity\Lecture;
use Kytarna\Model\Entity\LectureBoardStatus;
use Kytarna\Model\Entity\Song;
use Kytarna\Model\Entity\SongBoardStatus;
use Kytarna\Model\Entity\User;
use Kytarna\Model\Entity\Workspace;
use Kytarna\Model\Repository\LectureBoardStatusRepository;
use Kytarna\Model\Repository\SongBoardStatusRepository;

final readonly class ProgressStatusProvider implements ProgressStatusProviderInterface
{
	public function __construct(
		private LectureBoardStatusRepository $lectureBoardStatusRepository,
		private SongBoardStatusRepository $songBoardStatusRepository,
	) {
	}

	public function setLectureStatus(User $user, Lecture $lecture, LearningStatusEnum $status): LectureBoardStatus
	{
		$now = new DateTimeImmutable();
		$existing = $this->lectureBoardStatusRepository->findForUserAndLecture($user->id, $lecture->id);

		if ($existing !== null) {
			$existing->status = $status;
			$existing->updatedAt = $now;
			$this->lectureBoardStatusRepository->persist($existing);

			return $existing;
		}

		$entry = new LectureBoardStatus(user: $user, lecture: $lecture, status: $status);
		$entry->createdAt = $now;
		$entry->updatedAt = $now;
		$this->lectureBoardStatusRepository->persist($entry);

		return $entry;
	}

	public function setSongStatus(User $user, Song $song, LearningStatusEnum $status): SongBoardStatus
	{
		$now = new DateTimeImmutable();
		$existing = $this->songBoardStatusRepository->findForUserAndSong($user->id, $song->id);

		if ($existing !== null) {
			$existing->status = $status;
			$existing->updatedAt = $now;
			$this->songBoardStatusRepository->persist($existing);

			return $existing;
		}

		$entry = new SongBoardStatus(user: $user, song: $song, status: $status);
		$entry->createdAt = $now;
		$entry->updatedAt = $now;
		$this->songBoardStatusRepository->persist($entry);

		return $entry;
	}

	public function statusForLecture(User $user, Lecture $lecture): LearningStatusEnum
	{
		$row = $this->lectureBoardStatusRepository->findForUserAndLecture($user->id, $lecture->id);

		return $row === null ? $lecture->status : $row->status;
	}

	public function statusForSong(User $user, Song $song): LearningStatusEnum
	{
		$row = $this->songBoardStatusRepository->findForUserAndSong($user->id, $song->id);

		return $row === null ? $song->status : $row->status;
	}

	/** @return array<int, LearningStatusEnum> */
	public function lectureStatusesForUserInCourse(User $user, Course $course): array
	{
		$map = [];
		foreach ($this->lectureBoardStatusRepository->findAllForUserInCourse($user->id, $course->id) as $row) {
			$map[$row->lecture->id] = $row->status;
		}

		return $map;
	}

	/** @return array<int, LearningStatusEnum> */
	public function songStatusesForUserInCourse(User $user, Course $course): array
	{
		$map = [];
		foreach ($this->songBoardStatusRepository->findAllForUserInCourse($user->id, $course->id) as $row) {
			$map[$row->song->id] = $row->status;
		}

		return $map;
	}

	/** @return array<int, LearningStatusEnum> */
	public function songStatusesForUserInWorkspace(User $user, Workspace $workspace): array
	{
		$map = [];
		foreach ($this->songBoardStatusRepository->findAllForUserInWorkspace($user->id, $workspace->id) as $row) {
			$map[$row->song->id] = $row->status;
		}

		return $map;
	}

	public function deleteAllForLecture(int $lectureId): void
	{
		foreach ($this->lectureBoardStatusRepository->findByLecture($lectureId) as $row) {
			$this->lectureBoardStatusRepository->delete($row);
		}
	}

	public function deleteAllForSong(int $songId): void
	{
		foreach ($this->songBoardStatusRepository->findBySong($songId) as $row) {
			$this->songBoardStatusRepository->delete($row);
		}
	}
}
