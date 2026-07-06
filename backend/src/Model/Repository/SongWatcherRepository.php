<?php

declare(strict_types=1);

namespace Kytarna\Model\Repository;

use Iterator;
use Kytarna\Model\Entity\SongWatcher;
use MarekSkopal\ORM\Repository\AbstractRepository;

/** @extends AbstractRepository<SongWatcher> */
final class SongWatcherRepository extends AbstractRepository
{
	/** @return Iterator<SongWatcher> */
	public function findBySong(int $songId): Iterator
	{
		return $this->select()
			->where(['song_id' => $songId])
			->orderBy('id', 'ASC')
			->fetchAll();
	}

	public function findBySongAndUser(int $songId, int $userId): ?SongWatcher
	{
		return $this->findOne(['song_id' => $songId, 'user_id' => $userId]);
	}
}
