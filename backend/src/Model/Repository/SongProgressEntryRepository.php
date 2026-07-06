<?php

declare(strict_types=1);

namespace Kytarna\Model\Repository;

use Iterator;
use Kytarna\Model\Entity\SongProgressEntry;
use MarekSkopal\ORM\Query\Select;
use MarekSkopal\ORM\Repository\AbstractRepository;

/** @extends AbstractRepository<SongProgressEntry> */
final class SongProgressEntryRepository extends AbstractRepository
{
	public function findById(int $id): ?SongProgressEntry
	{
		return $this->findOne(['id' => $id]);
	}

	/** @return Iterator<SongProgressEntry> */
	public function findBySong(int $songId, ?string $from = null, ?string $to = null): Iterator
	{
		return $this->applyDateRange($this->select()->where(['song_id' => $songId]), $from, $to)
			->orderBy('practiced_at', 'ASC')
			->orderBy('id', 'ASC')
			->fetchAll();
	}

	/**
	 * @param Select<SongProgressEntry> $select
	 * @return Select<SongProgressEntry>
	 */
	private function applyDateRange(Select $select, ?string $from, ?string $to): Select
	{
		if ($from !== null) {
			$select->where(['practiced_at', '>=', $from]);
		}
		if ($to !== null) {
			$select->where(['practiced_at', '<=', $to]);
		}
		return $select;
	}
}
