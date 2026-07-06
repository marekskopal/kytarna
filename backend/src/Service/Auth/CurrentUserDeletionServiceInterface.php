<?php

declare(strict_types=1);

namespace Kytarna\Service\Auth;

use Kytarna\Model\Entity\User;

interface CurrentUserDeletionServiceInterface
{
	public function deleteSelf(User $user): void;
}
