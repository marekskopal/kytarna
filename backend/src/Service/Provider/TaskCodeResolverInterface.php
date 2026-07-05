<?php

declare(strict_types=1);

namespace Kytario\Service\Provider;

use Kytario\Model\Entity\Task;
use Kytario\Model\Entity\User;
use Kytario\Model\Entity\Workspace;

interface TaskCodeResolverInterface
{
	public function findByCode(Workspace $workspace, string $code): ?Task;

	public function resolve(Workspace $workspace, string $idOrCode): ?Task;

	public function resolveForUser(User $user, string $idOrCode): ?Task;
}
