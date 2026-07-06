<?php

declare(strict_types=1);

namespace Kytarna\Model\Repository;

use DateTimeImmutable;
use Kytarna\Model\Entity\PasswordResetToken;
use MarekSkopal\ORM\Repository\AbstractRepository;

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
