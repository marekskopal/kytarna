<?php

declare(strict_types=1);

namespace Kytario\Model\Repository;

use Iterator;
use Kytario\Model\Entity\LectureFile;
use MarekSkopal\ORM\Repository\AbstractRepository;

/** @extends AbstractRepository<LectureFile> */
final class LectureFileRepository extends AbstractRepository
{
	/** @return Iterator<LectureFile> */
	public function findByLecture(int $lectureId): Iterator
	{
		return $this->select()
			->where(['lecture_id' => $lectureId])
			->orderBy('id', 'ASC')
			->fetchAll();
	}

	public function findOneById(int $id): ?LectureFile
	{
		return $this->findOne(['id' => $id]);
	}

	/** @return Iterator<LectureFile> */
	public function findByUploader(int $userId): Iterator
	{
		return $this->select()
			->where(['uploaded_by_user_id' => $userId])
			->orderBy('id', 'ASC')
			->fetchAll();
	}
}
