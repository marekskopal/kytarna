<?php

declare(strict_types=1);

namespace Kytarna\Model\Repository;

use Iterator;
use Kytarna\Model\Entity\SongLink;
use MarekSkopal\ORM\Repository\AbstractRepository;

/** @extends AbstractRepository<SongLink> */
final class SongLinkRepository extends AbstractRepository
{
	public function findById(int $id): ?SongLink
	{
		return $this->findOne(['id' => $id]);
	}

	/** @return Iterator<SongLink> */
	public function findBySong(int $songId): Iterator
	{
		return $this->select()
			->where(['song_id' => $songId])
			->orderBy('id', 'ASC')
			->fetchAll();
	}
}
