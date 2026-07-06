<?php

declare(strict_types=1);

namespace Kytarna\Model\Repository;

use Iterator;
use Kytarna\Model\Entity\Workspace;
use MarekSkopal\ORM\Repository\AbstractRepository;

/** @extends AbstractRepository<Workspace> */
final class WorkspaceRepository extends AbstractRepository
{
	/** LIKE treats %/_ as wildcards; escape them so user input only ever matches literally. */
	private static function escapeLikePattern(string $value): string
	{
		return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
	}

	public function findWorkspaceById(int $workspaceId): ?Workspace
	{
		return $this->findOne(['id' => $workspaceId]);
	}

	/** @return Iterator<Workspace> */
	public function findAllWorkspaces(): Iterator
	{
		return $this->select()->orderBy('id', 'ASC')->fetchAll();
	}

	/** @return Iterator<Workspace> */
	public function findByOwner(int $ownerId): Iterator
	{
		return $this->select()->where(['owner_id' => $ownerId])->fetchAll();
	}

	public function findByJoinCode(string $joinCode): ?Workspace
	{
		return $this->findOne(['join_code' => $joinCode]);
	}

	/**
	 * Public teacher directory. Excludes the given workspace ids (the ones the user already belongs to).
	 *
	 * @param list<int> $excludeIds
	 * @return Iterator<Workspace>
	 */
	public function findPublic(?string $search, int $limit, int $offset, array $excludeIds = []): Iterator
	{
		$select = $this->select()->where(['is_public' => true]);

		if ($search !== null && $search !== '') {
			$select->where(['name', 'LIKE', '%' . self::escapeLikePattern($search) . '%']);
		}
		if ($excludeIds !== []) {
			$select->where(['id', 'NOT IN', $excludeIds]);
		}

		return $select
			->orderBy('name', 'ASC')
			->orderBy('id', 'ASC')
			->limit($limit)
			->offset($offset)
			->fetchAll();
	}
}
