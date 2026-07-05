<?php

declare(strict_types=1);

namespace Kytario\Service\Auth;

use Kytario\Model\Entity\User;

interface CurrentUserDeletionServiceInterface
{
	public function deleteSelf(User $user): void;
}
