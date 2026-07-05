<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use DateTimeImmutable;
use Iterator;
use MarekSkopal\ORM\Repository\AbstractRepository;
use Ukolio\Model\Entity\Invitation;

/** @extends AbstractRepository<Invitation> */
final class InvitationRepository extends AbstractRepository
{
	public function findByTokenHash(string $tokenHash): ?Invitation
	{
		return $this->findOne(['token_hash' => $tokenHash]);
	}

	public function countByWorkspaceSince(int $workspaceId, DateTimeImmutable $since): int
	{
		return $this->select()
			->where(['workspace_id' => $workspaceId])
			->where(['created_at', '>=', $since->format('Y-m-d H:i:s')])
			->count();
	}

	/** @return Iterator<Invitation> */
	public function findByWorkspace(int $workspaceId): Iterator
	{
		return $this->select()
			->where(['workspace_id' => $workspaceId])
			->orderBy('id', 'DESC')
			->fetchAll();
	}

	/** @return Iterator<Invitation> */
	public function findByInviter(int $userId): Iterator
	{
		return $this->select()
			->where(['inviter_id' => $userId])
			->orderBy('id', 'ASC')
			->fetchAll();
	}
}
