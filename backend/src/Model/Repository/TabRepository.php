<?php

declare(strict_types=1);

namespace Kytario\Model\Repository;

use Iterator;
use Kytario\Model\Entity\Tab;
use MarekSkopal\ORM\Repository\AbstractRepository;

/** @extends AbstractRepository<Tab> */
final class TabRepository extends AbstractRepository
{
	public function findById(int $id): ?Tab
	{
		return $this->findOne(['id' => $id]);
	}

	/** @return Iterator<Tab> */
	public function findByLecture(int $lectureId): Iterator
	{
		return $this->select()
			->where(['lecture_id' => $lectureId])
			->orderBy('id', 'ASC')
			->fetchAll();
	}
}
