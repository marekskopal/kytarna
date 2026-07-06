<?php

declare(strict_types=1);

namespace Kytarna\Model\Repository;

use Iterator;
use Kytarna\Model\Entity\SongFile;
use MarekSkopal\ORM\Repository\AbstractRepository;

/** @extends AbstractRepository<SongFile> */
final class SongFileRepository extends AbstractRepository
{
	/** @return Iterator<SongFile> */
	public function findBySong(int $songId): Iterator
	{
		return $this->select()
			->where(['song_id' => $songId])
			->orderBy('id', 'ASC')
			->fetchAll();
	}

	public function findOneById(int $id): ?SongFile
	{
		return $this->findOne(['id' => $id]);
	}
}
