<?php

declare(strict_types=1);

namespace Kytario\Model\Repository;

use Kytario\Model\Entity\EmailVerificationToken;
use MarekSkopal\ORM\Repository\AbstractRepository;

/** @extends AbstractRepository<EmailVerificationToken> */
final class EmailVerificationTokenRepository extends AbstractRepository
{
	public function findByTokenHash(string $tokenHash): ?EmailVerificationToken
	{
		return $this->findOne(['token_hash' => $tokenHash]);
	}
}
