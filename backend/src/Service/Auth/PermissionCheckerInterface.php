<?php

declare(strict_types=1);

namespace Kytarna\Service\Auth;

use Kytarna\Model\Entity\Enum\WorkspaceRoleEnum;
use Kytarna\Model\Entity\User;
use Kytarna\Model\Entity\Workspace;
use Kytarna\Model\Entity\WorkspaceUser;

interface PermissionCheckerInterface
{
	public function isSystemAdmin(User $user): bool;

	/** The Teacher is the workspace owner: the only member allowed to edit content and manage members. */
	public function isTeacher(User $user, Workspace $workspace): bool;

	public function canViewWorkspace(User $user, Workspace $workspace): bool;

	public function canManageWorkspace(User $user, Workspace $workspace): bool;

	public function canManageMembers(User $user, Workspace $workspace): bool;

	public function canRemoveMember(User $actor, Workspace $workspace, WorkspaceUser $target): bool;

	public function canManageCourses(User $user, Workspace $workspace): bool;

	public function canManageLectures(User $user, Workspace $workspace): bool;

	public function canManageSongs(User $user, Workspace $workspace): bool;

	public function canManageTags(User $user, Workspace $workspace): bool;

	/** Any member (Teacher or Student) may track their own learning progress and log practice. */
	public function canTrackProgress(User $user, Workspace $workspace): bool;

	public function canInviteAs(User $actor, Workspace $workspace, WorkspaceRoleEnum $role): bool;
}
