<?php

declare(strict_types=1);

namespace Kytario\Model\Repository;

use Iterator;
use Kytario\Model\Entity\LectureTag;
use MarekSkopal\ORM\Repository\AbstractRepository;

/** @extends AbstractRepository<LectureTag> */
final class LectureTagRepository extends AbstractRepository
{
	/** @return Iterator<LectureTag> */
	public function findByLecture(int $lectureId): Iterator
	{
		return $this->select()
			->where(['lecture_id' => $lectureId])
			->fetchAll();
	}

	/**
	 * @param list<int> $tagIds
	 * @return list<int> distinct lecture ids that have ANY of the given tags
	 */
	public function findLectureIdsByTagIds(array $tagIds): array
	{
		if ($tagIds === []) {
			return [];
		}
		$result = [];
		foreach ($this->select()->where(['tag_id', 'IN', $tagIds])->fetchAll() as $row) {
			$result[$row->lecture->id] = true;
		}
		return array_keys($result);
	}
}
