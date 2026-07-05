<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use DateTimeImmutable;
use MarekSkopal\ORM\Repository\AbstractRepository;
use Ukolio\Model\Entity\PasswordResetToken;

/** @extends AbstractRepository<PasswordResetToken> */
final class PasswordResetTokenRepository extends AbstractRepository
{
	public function findByTokenHash(string $tokenHash): ?PasswordResetToken
	{
		return $this->findOne(['token_hash' => $tokenHash]);
	}

	public function countByUserSince(int $userId, DateTimeImmutable $since): int
	{
		return $this->select()
			->where(['user_id' => $userId])
			->where(['created_at', '>=', $since->format('Y-m-d H:i:s')])
			->count();
	}
}
