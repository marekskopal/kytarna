<?php

declare(strict_types=1);

namespace Kytarna\Model\Repository;

use Iterator;
use Kytarna\Model\Entity\SongTag;
use MarekSkopal\ORM\Repository\AbstractRepository;

/** @extends AbstractRepository<SongTag> */
final class SongTagRepository extends AbstractRepository
{
	/** @return Iterator<SongTag> */
	public function findBySong(int $songId): Iterator
	{
		return $this->select()
			->where(['song_id' => $songId])
			->fetchAll();
	}

	/**
	 * @param list<int> $tagIds
	 * @return list<int> distinct song ids that have ANY of the given tags
	 */
	public function findSongIdsByTagIds(array $tagIds): array
	{
		if ($tagIds === []) {
			return [];
		}
		$result = [];
		foreach ($this->select()->where(['tag_id', 'IN', $tagIds])->fetchAll() as $row) {
			$result[$row->song->id] = true;
		}
		return array_keys($result);
	}
}
