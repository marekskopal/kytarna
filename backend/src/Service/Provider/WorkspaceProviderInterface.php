<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use Iterator;
use Ukolio\Model\Entity\Enum\WorkspaceRoleEnum;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Model\Entity\WorkspaceUser;

interface WorkspaceProviderInterface
{
	public function getWorkspace(int $workspaceId): ?Workspace;

	/** @return Iterator<WorkspaceUser> */
	public function getMemberships(User $user): Iterator;

	/** @return Iterator<WorkspaceUser> */
	public function getMembers(Workspace $workspace): Iterator;

	public function findMembership(User $user, Workspace $workspace): ?WorkspaceUser;

	public function isMember(User $user, Workspace $workspace): bool;

	public function createWorkspace(User $owner, string $name): Workspace;

	public function updateWorkspace(Workspace $workspace, string $name): Workspace;

	public function deleteWorkspace(Workspace $workspace): void;

	public function addMember(Workspace $workspace, User $user, WorkspaceRoleEnum $role): WorkspaceUser;

	public function removeMember(WorkspaceUser $membership): void;

	public function changeMemberRole(User $actor, WorkspaceUser $membership, WorkspaceRoleEnum $newRole): WorkspaceUser;

	public function transferOwnership(User $actor, Workspace $workspace, WorkspaceUser $newOwnerMembership): void;

	public function switchCurrentWorkspace(User $user, Workspace $workspace): void;

	public function getCurrentWorkspace(User $user): ?Workspace;
}
