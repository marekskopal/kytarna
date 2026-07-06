<?php

declare(strict_types=1);

namespace Kytarna\Model\Entity;

use DateTimeImmutable;
use Kytarna\Model\Entity\Enum\LocaleEnum;
use Kytarna\Model\Entity\Enum\SystemRoleEnum;
use Kytarna\Model\Entity\Enum\ThemeEnum;
use Kytarna\Model\Repository\UserRepository;
use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\ColumnEnum;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Enum\Type;

#[Entity(repositoryClass: UserRepository::class)]
class User extends AEntity
{
	#[Column(type: Type::Int, default: 0)]
	public int $failedLoginAttempts = 0;

	#[Column(type: Type::Int, default: 0)]
	public int $tokenVersion = 0;

	#[Column(type: Type::Timestamp, nullable: true)]
	public ?DateTimeImmutable $lockedUntil = null;

	#[Column(type: Type::Timestamp, nullable: true)]
	public ?DateTimeImmutable $onboardingCompletedAt = null;

	#[Column(type: Type::String, nullable: true, default: null)]
	public ?string $googleId = null;

	#[Column(type: Type::Int, nullable: true, default: null)]
	public ?int $defaultSavedViewId = null;

	public function __construct(
		#[Column(type: Type::String)]
		public string $email,
		#[Column(type: Type::String, nullable: true)]
		public ?string $password,
		#[Column(type: Type::String)]
		public string $name,
		#[ColumnEnum(enum: LocaleEnum::class, default: LocaleEnum::En)]
		public LocaleEnum $locale = LocaleEnum::En,
		#[ColumnEnum(enum: ThemeEnum::class, default: ThemeEnum::System)]
		public ThemeEnum $theme = ThemeEnum::System,
		#[Column(type: Type::Int, nullable: true)]
		public ?int $currentWorkspaceId = null,
		#[ColumnEnum(enum: SystemRoleEnum::class, default: SystemRoleEnum::User)]
		public SystemRoleEnum $systemRole = SystemRoleEnum::User,
		#[Column(type: Type::Boolean, default: false)]
		public bool $emailVerified = false,
	) {
	}
}
