<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use Ukolio\Model\Entity\PasswordResetToken;
use Ukolio\Model\Entity\User;

interface PasswordResetProviderInterface
{
	public function requestReset(string $email): void;

	public function findByToken(string $token): ?PasswordResetToken;

	public function confirmReset(PasswordResetToken $token, string $newPassword): User;
}
