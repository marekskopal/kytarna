<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use Iterator;
use Kytarna\Model\Entity\Enum\WorkspaceRoleEnum;
use Kytarna\Model\Entity\Invitation;
use Kytarna\Model\Entity\User;
use Kytarna\Model\Entity\Workspace;

interface InvitationProviderInterface
{
	/** @return Iterator<Invitation> */
	public function getInvitations(Workspace $workspace): Iterator;

	public function findByToken(string $token): ?Invitation;

	public function createInvitation(User $inviter, Workspace $workspace, string $email, WorkspaceRoleEnum $role): Invitation;

	public function acceptInvitation(User $user, Invitation $invitation): void;

	public function deleteInvitation(Invitation $invitation): void;
}
