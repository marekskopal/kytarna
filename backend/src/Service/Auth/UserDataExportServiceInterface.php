<?php

declare(strict_types=1);

namespace Kytarna\Service\Auth;

use Kytarna\Model\Entity\User;

interface UserDataExportServiceInterface
{
	/** @return array<string, mixed> */
	public function export(User $user): array;
}
