<?php

declare(strict_types=1);

namespace Ukolio\Model\Entity;

use DateTimeImmutable;
use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Enum\Type;
use Ukolio\Model\Repository\EmailVerificationTokenRepository;

#[Entity(repositoryClass: EmailVerificationTokenRepository::class)]
class EmailVerificationToken extends AEntity
{
	public function __construct(
		#[ManyToOne(entityClass: User::class)]
		public readonly User $user,
		#[Column(type: Type::String, size: 64)]
		public string $tokenHash,
		#[Column(type: Type::Timestamp)]
		public DateTimeImmutable $expiresAt,
		#[Column(type: Type::Timestamp, nullable: true)]
		public ?DateTimeImmutable $usedAt = null,
	) {
	}
}
