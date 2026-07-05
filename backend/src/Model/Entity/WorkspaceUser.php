<?php

declare(strict_types=1);

namespace Kytario\Model\Entity;

use MarekSkopal\ORM\Attribute\ColumnEnum;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use Kytario\Model\Entity\Enum\WorkspaceRoleEnum;
use Kytario\Model\Repository\WorkspaceUserRepository;

#[Entity(repositoryClass: WorkspaceUserRepository::class)]
class WorkspaceUser extends AEntity
{
	public function __construct(
		#[ManyToOne(entityClass: Workspace::class)]
		public readonly Workspace $workspace,
		#[ManyToOne(entityClass: User::class)]
		public readonly User $user,
		#[ColumnEnum(enum: WorkspaceRoleEnum::class)]
		public WorkspaceRoleEnum $role,
	) {
	}
}
