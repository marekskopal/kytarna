<?php

declare(strict_types=1);

namespace Kytario\Service\Provider;

use Kytario\Model\Entity\EmailVerificationToken;
use Kytario\Model\Entity\User;

interface EmailVerificationProviderInterface
{
	public function requestVerification(User $user): void;

	public function findByToken(string $token): ?EmailVerificationToken;

	public function confirmVerification(EmailVerificationToken $token): User;
}
