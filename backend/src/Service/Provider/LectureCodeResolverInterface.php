<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use Kytarna\Model\Entity\Lecture;
use Kytarna\Model\Entity\User;
use Kytarna\Model\Entity\Workspace;

interface LectureCodeResolverInterface
{
	public function findByCode(Workspace $workspace, string $code): ?Lecture;

	public function resolve(Workspace $workspace, string $idOrCode): ?Lecture;

	public function resolveForUser(User $user, string $idOrCode): ?Lecture;
}
