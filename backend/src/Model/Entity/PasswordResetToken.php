<?php

declare(strict_types=1);

namespace Kytarna\Model\Entity;

use DateTimeImmutable;
use Kytarna\Model\Repository\PasswordResetTokenRepository;
use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Enum\Type;

#[Entity(repositoryClass: PasswordResetTokenRepository::class)]
class PasswordResetToken extends AEntity
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
