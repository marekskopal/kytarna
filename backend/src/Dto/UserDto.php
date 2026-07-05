<?php

declare(strict_types=1);

namespace Ukolio\Dto;

use DateTimeImmutable;
use Ukolio\Model\Entity\User;

final readonly class UserDto
{
	public function __construct(
		public int $id,
		public string $email,
		public string $name,
		public string $locale,
		public string $theme,
		public ?int $currentWorkspaceId,
		public ?int $defaultSavedViewId,
		public string $systemRole,
		public bool $emailVerified,
		public ?string $onboardingCompletedAt,
	) {
	}

	public static function fromEntity(User $user): self
	{
		return new self(
			id: $user->id,
			email: $user->email,
			name: $user->name,
			locale: $user->locale->value,
			theme: $user->theme->value,
			currentWorkspaceId: $user->currentWorkspaceId,
			defaultSavedViewId: $user->defaultSavedViewId,
			systemRole: $user->systemRole->value,
			emailVerified: $user->emailVerified,
			onboardingCompletedAt: $user->onboardingCompletedAt?->format(DateTimeImmutable::ATOM),
		);
	}
}
