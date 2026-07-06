<?php

declare(strict_types=1);

namespace Kytarna\Model\Repository;

use Iterator;
use Kytarna\Model\Entity\Enum\LearningStatusEnum;
use Kytarna\Model\Entity\Song;
use Kytarna\Model\Repository\Enum\ArchivedFilterEnum;
use Kytarna\Model\Repository\Enum\LectureOrderByEnum;
use Kytarna\Model\Repository\Enum\OrderDirectionEnum;
use MarekSkopal\ORM\Query\Expression\RawExpression;
use MarekSkopal\ORM\Query\Select;
use MarekSkopal\ORM\Repository\AbstractRepository;

/** @extends AbstractRepository<Song> */
final class SongRepository extends AbstractRepository
{
	public function findById(int $songId): ?Song
	{
		return $this->findOne(['id' => $songId]);
	}

	/** LIKE treats %/_ as wildcards; escape them so user input only ever matches literally. */
	private static function escapeLikePattern(string $value): string
	{
		return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
	}

	/** Highest song sequence number in the course, 0 when the course has no songs. */
	public function maxSequenceNumber(int $courseId): int
	{
		$max = 0;
		foreach ($this->findByCourse($courseId) as $song) {
			if ($song->sequenceNumber !== null && $song->sequenceNumber > $max) {
				$max = $song->sequenceNumber;
			}
		}
		return $max;
	}

	/** @return Iterator<Song> */
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

	/**
	 * Songs sharing a board/library "column": the same workspace, course (possibly null = standalone), and status.
	 *
	 * @return Iterator<Song>
	 */
	public function findSiblings(int $workspaceId, ?int $courseId, LearningStatusEnum $status): Iterator
	{
		$select = $this->select()
			->where(['workspace_id' => $workspaceId])
			->where(['status', '=', $status]);

		if ($courseId === null) {
			$select->where([new RawExpression('(course_id IS NULL)'), '=', 1]);
		} else {
			$select->where(['course_id' => $courseId]);
		}

		return $select
			->orderBy('position', 'ASC')
			->fetchAll();
	}

	/**
	 * @param list<LearningStatusEnum>|null $statuses
	 * @return Iterator<Song>
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
		ArchivedFilterEnum $archived = ArchivedFilterEnum::Active,
	): Iterator {
		$select = $this->buildWorkspaceSelect($workspaceId, $search, $statuses, $onlyActive, $archived);

		$select->orderBy($orderBy->value, $direction->value);
		if ($orderBy !== LectureOrderByEnum::CreatedAt) {
			$select->orderBy('created_at', OrderDirectionEnum::Desc->value);
		}
		$select->orderBy('id', OrderDirectionEnum::Desc->value);

		return $select
			->limit($limit)
			->offset($offset)
			->fetchAll();
	}

	/** @param list<LearningStatusEnum>|null $statuses */
	public function countInWorkspace(
		int $workspaceId,
		?string $search,
		?array $statuses,
		bool $onlyActive,
		ArchivedFilterEnum $archived = ArchivedFilterEnum::Active,
	): int {
		return $this->buildWorkspaceSelect($workspaceId, $search, $statuses, $onlyActive, $archived)->count();
	}

	/**
	 * @param list<LearningStatusEnum>|null $statuses
	 * @return Select<Song>
	 */
	private function buildWorkspaceSelect(
		int $workspaceId,
		?string $search,
		?array $statuses,
		bool $onlyActive,
		ArchivedFilterEnum $archived,
	): Select {
		$select = $this->select()
			->where(['workspace_id' => $workspaceId]);

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

		return $select;
	}

	/**
	 * The ORM's where-builder has no IS NULL operator, so the archived filter is a parenthesised raw predicate.
	 *
	 * @param Select<Song> $select
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
