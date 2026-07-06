<?php

declare(strict_types=1);

namespace Kytarna\Dto;

use Kytarna\Model\Entity\Invitation;
use const DATE_ATOM;

final readonly class InvitationDto
{
	public function __construct(
		public int $id,
		public int $workspaceId,
		public string $workspaceName,
		public string $email,
		public string $inviterName,
		public string $role,
		public string $expiresAt,
		public ?string $acceptedAt,
	) {
	}

	public static function fromEntity(Invitation $invitation): self
	{
		return new self(
			id: $invitation->id,
			workspaceId: $invitation->workspace->id,
			workspaceName: $invitation->workspace->name,
			email: $invitation->email,
			inviterName: $invitation->inviter->name,
			role: $invitation->role->value,
			expiresAt: $invitation->expiresAt->format(DATE_ATOM),
			acceptedAt: $invitation->acceptedAt?->format(DATE_ATOM),
		);
	}
}
