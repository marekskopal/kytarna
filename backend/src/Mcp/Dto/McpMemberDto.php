<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Dto;

use Ukolio\Model\Entity\WorkspaceUser;

final readonly class McpMemberDto
{
	public function __construct(public int $userId, public string $name, public string $email, public string $role,)
	{
	}

	public static function fromEntity(WorkspaceUser $membership): self
	{
		return new self(
			userId: $membership->user->id,
			name: $membership->user->name,
			email: $membership->user->email,
			role: $membership->role->value,
		);
	}
}
