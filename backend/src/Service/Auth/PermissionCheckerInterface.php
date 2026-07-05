<?php

declare(strict_types=1);

namespace Ukolio\Service\Auth;

use Ukolio\Model\Entity\Enum\WorkspaceRoleEnum;
use Ukolio\Model\Entity\TaskComment;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Model\Entity\WorkspaceUser;

interface PermissionCheckerInterface
{
	public function isSystemAdmin(User $user): bool;

	public function canViewWorkspace(User $user, Workspace $workspace): bool;

	public function canManageWorkspace(User $user, Workspace $workspace): bool;

	public function canManageMembers(User $user, Workspace $workspace): bool;

	public function canRemoveMember(User $actor, Workspace $workspace, WorkspaceUser $target): bool;

	public function canChangeRole(User $actor, Workspace $workspace, WorkspaceUser $target, WorkspaceRoleEnum $newRole): bool;

	public function canManageProjects(User $user, Workspace $workspace): bool;

	public function canManageTasks(User $user, Workspace $workspace): bool;

	public function canManageFields(User $user, Workspace $workspace): bool;

	public function canManageTags(User $user, Workspace $workspace): bool;

	public function canManagePriorities(User $user, Workspace $workspace): bool;

	public function canManageTaskTemplates(User $user, Workspace $workspace): bool;

	public function canManageScripts(User $user, Workspace $workspace): bool;

	public function canInviteAs(User $actor, Workspace $workspace, WorkspaceRoleEnum $role): bool;

	public function canDeleteTaskComment(User $user, Workspace $workspace, TaskComment $comment): bool;

	public function canEditTaskComment(User $user, Workspace $workspace, TaskComment $comment): bool;
}
