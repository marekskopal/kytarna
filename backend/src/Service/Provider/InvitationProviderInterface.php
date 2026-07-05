<?php

declare(strict_types=1);

namespace Kytario\Service\Provider;

use Iterator;
use Kytario\Model\Entity\Enum\WorkspaceRoleEnum;
use Kytario\Model\Entity\Invitation;
use Kytario\Model\Entity\User;
use Kytario\Model\Entity\Workspace;

interface InvitationProviderInterface
{
	/** @return Iterator<Invitation> */
	public function getInvitations(Workspace $workspace): Iterator;

	public function findByToken(string $token): ?Invitation;

	public function createInvitation(User $inviter, Workspace $workspace, string $email, WorkspaceRoleEnum $role): Invitation;

	public function acceptInvitation(User $user, Invitation $invitation): void;

	public function deleteInvitation(Invitation $invitation): void;
}
