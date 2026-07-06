<?php

declare(strict_types=1);

namespace Kytarna\Model\Entity;

use DateTimeImmutable;
use Kytarna\Model\Entity\Enum\WorkspaceRoleEnum;
use Kytarna\Model\Repository\InvitationRepository;
use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\ColumnEnum;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Enum\Type;

#[Entity(repositoryClass: InvitationRepository::class)]
class Invitation extends AEntity
{
	public function __construct(
		#[ManyToOne(entityClass: Workspace::class)]
		public readonly Workspace $workspace,
		#[ManyToOne(entityClass: User::class)]
		public readonly User $inviter,
		#[Column(type: Type::String)]
		public string $email,
		#[Column(type: Type::String, size: 64)]
		public string $tokenHash,
		#[ColumnEnum(enum: WorkspaceRoleEnum::class)]
		public WorkspaceRoleEnum $role,
		#[Column(type: Type::Timestamp)]
		public DateTimeImmutable $expiresAt,
		#[Column(type: Type::Timestamp, nullable: true)]
		public ?DateTimeImmutable $acceptedAt = null,
	) {
	}
}
