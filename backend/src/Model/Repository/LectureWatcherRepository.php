<?php

declare(strict_types=1);

namespace Kytario\Model\Repository;

use Iterator;
use Kytario\Model\Entity\LectureWatcher;
use MarekSkopal\ORM\Repository\AbstractRepository;

/** @extends AbstractRepository<LectureWatcher> */
final class LectureWatcherRepository extends AbstractRepository
{
	/** @return Iterator<LectureWatcher> */
	public function findByLecture(int $lectureId): Iterator
	{
		return $this->select()
			->where(['lecture_id' => $lectureId])
			->orderBy('id', 'ASC')
			->fetchAll();
	}

	public function findByLectureAndUser(int $lectureId, int $userId): ?LectureWatcher
	{
		return $this->findOne(['lecture_id' => $lectureId, 'user_id' => $userId]);
	}
}
