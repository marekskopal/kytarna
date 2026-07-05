<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use Ukolio\Model\Entity\EmailVerificationToken;
use Ukolio\Model\Entity\User;

interface EmailVerificationProviderInterface
{
	public function requestVerification(User $user): void;

	public function findByToken(string $token): ?EmailVerificationToken;

	public function confirmVerification(EmailVerificationToken $token): User;
}
