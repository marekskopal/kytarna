<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use Kytarna\Model\Entity\Course;
use Kytarna\Model\Entity\Enum\LearningStatusEnum;
use Kytarna\Model\Entity\Lecture;
use Kytarna\Model\Entity\LectureBoardStatus;
use Kytarna\Model\Entity\Song;
use Kytarna\Model\Entity\SongBoardStatus;
use Kytarna\Model\Entity\User;
use Kytarna\Model\Entity\Workspace;

/**
 * Per-user board column ("my progress") for lectures and songs. This is the overlay that makes the
 * board personal: the viewing user sees their own ToLearn / Learning / Mastered per item, independent
 * of the teacher-authored default on the item itself.
 */
interface ProgressStatusProviderInterface
{
	public function setLectureStatus(User $user, Lecture $lecture, LearningStatusEnum $status): LectureBoardStatus;

	public function setSongStatus(User $user, Song $song, LearningStatusEnum $status): SongBoardStatus;

	/** The user's personal status for the lecture, falling back to the lecture's authored default. */
	public function statusForLecture(User $user, Lecture $lecture): LearningStatusEnum;

	public function statusForSong(User $user, Song $song): LearningStatusEnum;

	/** @return array<int, LearningStatusEnum> keyed by lecture id */
	public function lectureStatusesForUserInCourse(User $user, Course $course): array;

	/** @return array<int, LearningStatusEnum> keyed by song id */
	public function songStatusesForUserInCourse(User $user, Course $course): array;

	/** @return array<int, LearningStatusEnum> keyed by song id */
	public function songStatusesForUserInWorkspace(User $user, Workspace $workspace): array;

	public function deleteAllForLecture(int $lectureId): void;

	public function deleteAllForSong(int $songId): void;
}
