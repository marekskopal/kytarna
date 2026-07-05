<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use MarekSkopal\ORM\Repository\AbstractRepository;
use Ukolio\Model\Entity\EmailVerificationToken;

/** @extends AbstractRepository<EmailVerificationToken> */
final class EmailVerificationTokenRepository extends AbstractRepository
{
	public function findByTokenHash(string $tokenHash): ?EmailVerificationToken
	{
		return $this->findOne(['token_hash' => $tokenHash]);
	}
}
