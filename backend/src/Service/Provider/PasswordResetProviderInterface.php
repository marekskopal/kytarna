<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use Kytarna\Model\Entity\PasswordResetToken;
use Kytarna\Model\Entity\User;

interface PasswordResetProviderInterface
{
	public function requestReset(string $email): void;

	public function findByToken(string $token): ?PasswordResetToken;

	public function confirmReset(PasswordResetToken $token, string $newPassword): User;
}
