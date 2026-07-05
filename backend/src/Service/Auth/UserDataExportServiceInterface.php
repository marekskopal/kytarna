<?php

declare(strict_types=1);

namespace Ukolio\Service\Auth;

use Ukolio\Model\Entity\User;

interface UserDataExportServiceInterface
{
	/** @return array<string, mixed> */
	public function export(User $user): array;
}
