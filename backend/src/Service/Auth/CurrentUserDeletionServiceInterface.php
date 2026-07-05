<?php

declare(strict_types=1);

namespace Ukolio\Service\Auth;

use Ukolio\Model\Entity\User;

interface CurrentUserDeletionServiceInterface
{
	public function deleteSelf(User $user): void;
}
