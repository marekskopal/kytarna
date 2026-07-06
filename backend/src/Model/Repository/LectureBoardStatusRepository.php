<?php

declare(strict_types=1);

namespace Kytarna\Model\Repository;

use Iterator;
use Kytarna\Model\Entity\LectureBoardStatus;
use MarekSkopal\ORM\Repository\AbstractRepository;

/** @extends AbstractRepository<LectureBoardStatus> */
final class LectureBoardStatusRepository extends AbstractRepository
{
	public function findForUserAndLecture(int $userId, int $lectureId): ?LectureBoardStatus
	{
		return $this->findOne(['user_id' => $userId, 'lecture_id' => $lectureId]);
	}

	/** @return Iterator<LectureBoardStatus> */
	public function findAllForUserInCourse(int $userId, int $courseId): Iterator
	{
		return $this->select()
			->where(['user_id' => $userId, 'lecture.course_id' => $courseId])
			->fetchAll();
	}

	/** @return Iterator<LectureBoardStatus> */
	public function findByLecture(int $lectureId): Iterator
	{
		return $this->select()->where(['lecture_id' => $lectureId])->fetchAll();
	}
}
