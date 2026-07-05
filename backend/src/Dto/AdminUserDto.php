<?php

declare(strict_types=1);

namespace Ukolio\Dto;

use Ukolio\Model\Entity\User;

final readonly class AdminUserDto
{
	public function __construct(
		public int $id,
		public string $email,
		public string $name,
		public string $locale,
		public string $systemRole,
		public int $workspaceCount,
		public int $ownedWorkspaceCount,
	) {
	}

	public static function fromEntity(User $user, int $workspaceCount, int $ownedWorkspaceCount): self
	{
		return new self(
			id: $user->id,
			email: $user->email,
			name: $user->name,
			locale: $user->locale->value,
			systemRole: $user->systemRole->value,
			workspaceCount: $workspaceCount,
			ownedWorkspaceCount: $ownedWorkspaceCount,
		);
	}
}
