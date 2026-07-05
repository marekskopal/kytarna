<?php

declare(strict_types=1);

namespace Kytario\Model\Repository;

use Iterator;
use Kytario\Model\Entity\ProgressEntry;
use MarekSkopal\ORM\Query\Select;
use MarekSkopal\ORM\Repository\AbstractRepository;

/** @extends AbstractRepository<ProgressEntry> */
final class ProgressEntryRepository extends AbstractRepository
{
	public function findById(int $id): ?ProgressEntry
	{
		return $this->findOne(['id' => $id]);
	}

	/** @return Iterator<ProgressEntry> */
	public function findByLecture(int $lectureId, ?string $from = null, ?string $to = null): Iterator
	{
		return $this->applyDateRange($this->select()->where(['lecture_id' => $lectureId]), $from, $to)
			->orderBy('practiced_at', 'ASC')
			->orderBy('id', 'ASC')
			->fetchAll();
	}

	/** @return Iterator<ProgressEntry> */
	public function findByCourse(int $courseId, ?string $from = null, ?string $to = null): Iterator
	{
		return $this->applyDateRange($this->select()->where(['lecture.course_id' => $courseId]), $from, $to)
			->orderBy('practiced_at', 'ASC')
			->orderBy('id', 'ASC')
			->fetchAll();
	}

	/**
	 * @param Select<ProgressEntry> $select
	 * @return Select<ProgressEntry>
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
