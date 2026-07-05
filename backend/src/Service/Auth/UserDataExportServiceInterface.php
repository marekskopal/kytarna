<?php

declare(strict_types=1);

namespace Kytario\Service\Auth;

use Kytario\Model\Entity\User;

interface UserDataExportServiceInterface
{
	/** @return array<string, mixed> */
	public function export(User $user): array;
}
