<?php

declare(strict_types=1);

namespace Kytario\Service\Provider;

use Kytario\Model\Entity\Lecture;
use Kytario\Model\Entity\User;
use Kytario\Model\Entity\Workspace;

interface LectureCodeResolverInterface
{
	public function findByCode(Workspace $workspace, string $code): ?Lecture;

	public function resolve(Workspace $workspace, string $idOrCode): ?Lecture;

	public function resolveForUser(User $user, string $idOrCode): ?Lecture;
}
