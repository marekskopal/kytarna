<?php

declare(strict_types=1);

namespace Kytario\Service\Provider;

use Kytario\Model\Entity\PasswordResetToken;
use Kytario\Model\Entity\User;

interface PasswordResetProviderInterface
{
	public function requestReset(string $email): void;

	public function findByToken(string $token): ?PasswordResetToken;

	public function confirmReset(PasswordResetToken $token, string $newPassword): User;
}
