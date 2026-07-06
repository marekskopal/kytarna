<?php

declare(strict_types=1);

namespace Kytarna\Model\Repository;

use Iterator;
use Kytarna\Model\Entity\LectureLink;
use MarekSkopal\ORM\Repository\AbstractRepository;

/** @extends AbstractRepository<LectureLink> */
final class LectureLinkRepository extends AbstractRepository
{
	public function findById(int $id): ?LectureLink
	{
		return $this->findOne(['id' => $id]);
	}

	/** @return Iterator<LectureLink> */
	public function findByLecture(int $lectureId): Iterator
	{
		return $this->select()
			->where(['lecture_id' => $lectureId])
			->orderBy('id', 'ASC')
			->fetchAll();
	}
}
