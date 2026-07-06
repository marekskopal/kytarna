<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use Kytarna\Model\Entity\EmailVerificationToken;
use Kytarna\Model\Entity\User;

interface EmailVerificationProviderInterface
{
	public function requestVerification(User $user): void;

	public function findByToken(string $token): ?EmailVerificationToken;

	public function confirmVerification(EmailVerificationToken $token): User;
}
