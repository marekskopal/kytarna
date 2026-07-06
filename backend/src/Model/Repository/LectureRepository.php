<?php

declare(strict_types=1);

namespace Kytarna\Model\Repository;

use EmptyIterator;
use Iterator;
use Kytarna\Model\Entity\Enum\LearningStatusEnum;
use Kytarna\Model\Entity\Lecture;
use Kytarna\Model\Repository\Enum\ArchivedFilterEnum;
use Kytarna\Model\Repository\Enum\LectureOrderByEnum;
use Kytarna\Model\Repository\Enum\OrderDirectionEnum;
use MarekSkopal\ORM\Query\Expression\RawExpression;
use MarekSkopal\ORM\Query\Select;
use MarekSkopal\ORM\Repository\AbstractRepository;

/** @extends AbstractRepository<Lecture> */
final class LectureRepository extends AbstractRepository
{
	public function findById(int $lectureId): ?Lecture
	{
		return $this->findOne(['id' => $lectureId]);
	}

	/** LIKE treats %/_ as wildcards; escape them so user input only ever matches literally. */
	private static function escapeLikePattern(string $value): string
	{
		return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
	}

	public function findByCourseAndSequence(int $courseId, int $sequenceNumber): ?Lecture
	{
		return $this->findOne(['course_id' => $courseId, 'sequence_number' => $sequenceNumber]);
	}

	/**
	 * @param list<int> $lectureIds
	 * @return Iterator<Lecture>
	 */
	public function findByIds(array $lectureIds): Iterator
	{
		if ($lectureIds === []) {
			return new EmptyIterator();
		}
		return $this->select()
			->where(['id', 'IN', $lectureIds])
			->fetchAll();
	}

	public function nextSequenceNumber(int $courseId): int
	{
		return $this->maxSequenceNumber($courseId) + 1;
	}

	/** Highest lecture sequence number in the course, 0 when the course has no lectures. */
	public function maxSequenceNumber(int $courseId): int
	{
		$max = 0;
		foreach ($this->findByCourse($courseId) as $lecture) {
			if ($lecture->sequenceNumber > $max) {
				$max = $lecture->sequenceNumber;
			}
		}
		return $max;
	}

	/** @return Iterator<Lecture> */
	public function findByCourse(int $courseId, bool $includeArchived = true): Iterator
	{
		$select = $this->select()
			->where(['course_id' => $courseId]);

		if (!$includeArchived) {
			$this->applyArchivedFilter($select, ArchivedFilterEnum::Active);
		}

		return $select
			->orderBy('status', 'ASC')
			->orderBy('position', 'ASC')
			->fetchAll();
	}

	/** @return Iterator<Lecture> */
	public function findByCourseAndStatus(int $courseId, LearningStatusEnum $status): Iterator
	{
		return $this->select()
			->where(['course_id' => $courseId])
			->where(['status', '=', $status])
			->orderBy('position', 'ASC')
			->fetchAll();
	}

	/**
	 * @param list<LearningStatusEnum>|null $statuses
	 * @param list<int>|null $lectureIdsFilter restrict to these IDs; pass [] to force an empty result
	 * @param list<int>|null $excludeLectureIds drop these IDs from the result
	 * @return Iterator<Lecture>
	 */
	public function findInWorkspace(
		int $workspaceId,
		int $limit,
		int $offset,
		LectureOrderByEnum $orderBy,
		OrderDirectionEnum $direction,
		?string $search,
		?array $statuses,
		bool $onlyActive,
		?array $lectureIdsFilter = null,
		?array $excludeLectureIds = null,
		ArchivedFilterEnum $archived = ArchivedFilterEnum::Active,
	): Iterator {
		if ($lectureIdsFilter !== null && $lectureIdsFilter === []) {
			return new EmptyIterator();
		}

		$select = $this->buildWorkspaceSelect(
			$workspaceId,
			$search,
			$statuses,
			$onlyActive,
			$lectureIdsFilter,
			$excludeLectureIds,
			$archived,
		);

		$select->orderBy($orderBy->value, $direction->value);

		// Secondary deterministic order so equal-key rows stay stable across pages.
		if ($orderBy !== LectureOrderByEnum::CreatedAt) {
			$select->orderBy('created_at', OrderDirectionEnum::Desc->value);
		}
		$select->orderBy('id', OrderDirectionEnum::Desc->value);

		return $select
			->limit($limit)
			->offset($offset)
			->fetchAll();
	}

	/**
	 * @param list<LearningStatusEnum>|null $statuses
	 * @param list<int>|null $lectureIdsFilter
	 * @param list<int>|null $excludeLectureIds
	 */
	public function countInWorkspace(
		int $workspaceId,
		?string $search,
		?array $statuses,
		bool $onlyActive,
		?array $lectureIdsFilter = null,
		?array $excludeLectureIds = null,
		ArchivedFilterEnum $archived = ArchivedFilterEnum::Active,
	): int {
		if ($lectureIdsFilter !== null && $lectureIdsFilter === []) {
			return 0;
		}
		return $this->buildWorkspaceSelect($workspaceId, $search, $statuses, $onlyActive, $lectureIdsFilter, $excludeLectureIds, $archived)
			->count();
	}

	/**
	 * @param list<LearningStatusEnum>|null $statuses
	 * @param list<int>|null $lectureIdsFilter
	 * @param list<int>|null $excludeLectureIds
	 * @return Select<Lecture>
	 */
	private function buildWorkspaceSelect(
		int $workspaceId,
		?string $search,
		?array $statuses,
		bool $onlyActive,
		?array $lectureIdsFilter = null,
		?array $excludeLectureIds = null,
		ArchivedFilterEnum $archived = ArchivedFilterEnum::Active,
	): Select {
		$select = $this->select()
			->where(['course.workspace_id' => $workspaceId]);

		$this->applyArchivedFilter($select, $archived);

		if ($search !== null && $search !== '') {
			$select->where(['name', 'LIKE', '%' . self::escapeLikePattern($search) . '%']);
		}
		if ($statuses !== null && $statuses !== []) {
			$select->where(['status', 'IN', array_map(static fn (LearningStatusEnum $s): string => $s->value, $statuses)]);
		}
		if ($onlyActive) {
			$select->where(['status', '!=', LearningStatusEnum::Mastered]);
		}
		if ($lectureIdsFilter !== null && $lectureIdsFilter !== []) {
			$select->where(['id', 'IN', $lectureIdsFilter]);
		}
		if ($excludeLectureIds !== null && $excludeLectureIds !== []) {
			$select->where(['id', 'NOT IN', $excludeLectureIds]);
		}

		return $select;
	}

	/**
	 * The ORM's where-builder has no IS NULL operator (a null value binds as `col = ?`, which never
	 * matches), so the archived filter is expressed as a parenthesised raw predicate compared to 1.
	 * `archived_at` exists only on the lectures table, so the unqualified reference is unambiguous even
	 * when course is joined.
	 *
	 * @param Select<Lecture> $select
	 */
	private function applyArchivedFilter(Select $select, ArchivedFilterEnum $archived): void
	{
		if ($archived === ArchivedFilterEnum::All) {
			return;
		}

		$predicate = $archived === ArchivedFilterEnum::Active
			? 'archived_at IS NULL'
			: 'archived_at IS NOT NULL';

		$select->where([new RawExpression('(' . $predicate . ')'), '=', 1]);
	}
}
