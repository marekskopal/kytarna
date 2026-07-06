<?php

declare(strict_types=1);

namespace Kytarna\Model\Repository;

use Iterator;
use Kytarna\Model\Entity\SongTab;
use MarekSkopal\ORM\Repository\AbstractRepository;

/** @extends AbstractRepository<SongTab> */
final class SongTabRepository extends AbstractRepository
{
	public function findById(int $id): ?SongTab
	{
		return $this->findOne(['id' => $id]);
	}

	/** @return Iterator<SongTab> */
	public function findBySong(int $songId): Iterator
	{
		return $this->select()
			->where(['song_id' => $songId])
			->orderBy('id', 'ASC')
			->fetchAll();
	}
}
