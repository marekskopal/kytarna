<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use Iterator;
use MarekSkopal\ORM\Repository\AbstractRepository;
use Ukolio\Model\Entity\OAuthClient;

/** @extends AbstractRepository<OAuthClient> */
final class OAuthClientRepository extends AbstractRepository
{
	public function findByClientId(string $clientId): ?OAuthClient
	{
		return $this->findOne(['client_id' => $clientId]);
	}

	/** @return Iterator<OAuthClient> */
	public function findByUser(int $userId): Iterator
	{
		return $this->select()
			->where(['user_id' => $userId])
			->orderBy('id', 'ASC')
			->fetchAll();
	}
}
