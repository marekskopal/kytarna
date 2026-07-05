<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use Ukolio\Model\Entity\Task;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workspace;

interface TaskCodeResolverInterface
{
	public function findByCode(Workspace $workspace, string $code): ?Task;

	public function resolve(Workspace $workspace, string $idOrCode): ?Task;

	public function resolveForUser(User $user, string $idOrCode): ?Task;
}
