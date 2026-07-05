<?php

declare(strict_types=1);

namespace Kytario\Service\Auth;

use Kytario\Model\Entity\Enum\WorkspaceRoleEnum;
use Kytario\Model\Entity\User;
use Kytario\Model\Entity\Workspace;
use Kytario\Model\Entity\WorkspaceUser;

interface PermissionCheckerInterface
{
	public function isSystemAdmin(User $user): bool;

	public function canViewWorkspace(User $user, Workspace $workspace): bool;

	public function canManageWorkspace(User $user, Workspace $workspace): bool;

	public function canManageMembers(User $user, Workspace $workspace): bool;

	public function canRemoveMember(User $actor, Workspace $workspace, WorkspaceUser $target): bool;

	public function canChangeRole(User $actor, Workspace $workspace, WorkspaceUser $target, WorkspaceRoleEnum $newRole): bool;

	public function canManageCourses(User $user, Workspace $workspace): bool;

	public function canManageLectures(User $user, Workspace $workspace): bool;

	public function canManageTags(User $user, Workspace $workspace): bool;

	public function canInviteAs(User $actor, Workspace $workspace, WorkspaceRoleEnum $role): bool;
}
