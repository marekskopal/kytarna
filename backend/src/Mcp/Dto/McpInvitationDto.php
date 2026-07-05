<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Dto;

use Ukolio\Model\Entity\Invitation;
use const DATE_ATOM;

final readonly class McpInvitationDto
{
	public function __construct(
		public int $id,
		public string $email,
		public string $role,
		public string $expiresAt,
		public ?string $acceptedAt,
	) {
	}

	public static function fromEntity(Invitation $invitation): self
	{
		return new self(
			id: $invitation->id,
			email: $invitation->email,
			role: $invitation->role->value,
			expiresAt: $invitation->expiresAt->format(DATE_ATOM),
			acceptedAt: $invitation->acceptedAt?->format(DATE_ATOM),
		);
	}
}
