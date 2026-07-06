<?php

declare(strict_types=1);

namespace Kytarna\Service\Auth;

use Kytarna\Model\Entity\Enum\SystemRoleEnum;
use Kytarna\Model\Entity\Enum\WorkspaceRoleEnum;
use Kytarna\Model\Entity\User;
use Kytarna\Model\Entity\Workspace;
use Kytarna\Model\Entity\WorkspaceUser;
use Kytarna\Service\Provider\WorkspaceProviderInterface;

/**
 * Central authorization for the Teacher / Student model. A workspace has exactly one Teacher (its
 * owner), who creates and edits all content and manages members; every other member is a Student
 * with read-only content and personal progress tracking. SystemAdmin short-circuits to allowed.
 */
final readonly class PermissionChecker implements PermissionCheckerInterface
{
	public function __construct(private WorkspaceProviderInterface $workspaceProvider)
	{
	}

	public function isSystemAdmin(User $user): bool
	{
		return $user->systemRole === SystemRoleEnum::SystemAdmin;
	}

	public function isTeacher(User $user, Workspace $workspace): bool
	{
		$membership = $this->workspaceProvider->findMembership($user, $workspace);
		return $membership !== null && $membership->role === WorkspaceRoleEnum::Teacher;
	}

	public function canViewWorkspace(User $user, Workspace $workspace): bool
	{
		return $this->isSystemAdmin($user) || $this->workspaceProvider->isMember($user, $workspace);
	}

	public function canManageWorkspace(User $user, Workspace $workspace): bool
	{
		return $this->isSystemAdmin($user) || $this->isTeacher($user, $workspace);
	}

	public function canManageMembers(User $user, Workspace $workspace): bool
	{
		return $this->isSystemAdmin($user) || $this->isTeacher($user, $workspace);
	}

	public function canRemoveMember(User $actor, Workspace $workspace, WorkspaceUser $target): bool
	{
		// The Teacher (owner) cannot be removed or leave — the workspace must be deleted instead.
		if ($target->role === WorkspaceRoleEnum::Teacher) {
			return false;
		}

		// A Student may always remove themselves (leave the workspace).
		if ($target->user->id === $actor->id) {
			return true;
		}

		return $this->isSystemAdmin($actor) || $this->isTeacher($actor, $workspace);
	}

	public function canManageCourses(User $user, Workspace $workspace): bool
	{
		return $this->isSystemAdmin($user) || $this->isTeacher($user, $workspace);
	}

	public function canManageLectures(User $user, Workspace $workspace): bool
	{
		return $this->isSystemAdmin($user) || $this->isTeacher($user, $workspace);
	}

	public function canManageSongs(User $user, Workspace $workspace): bool
	{
		return $this->isSystemAdmin($user) || $this->isTeacher($user, $workspace);
	}

	public function canManageTags(User $user, Workspace $workspace): bool
	{
		return $this->isSystemAdmin($user) || $this->isTeacher($user, $workspace);
	}

	public function canTrackProgress(User $user, Workspace $workspace): bool
	{
		return $this->isSystemAdmin($user) || $this->workspaceProvider->isMember($user, $workspace);
	}

	public function canInviteAs(User $actor, Workspace $workspace, WorkspaceRoleEnum $role): bool
	{
		// Only Students can be invited; the single Teacher is the owner.
		if ($role !== WorkspaceRoleEnum::Student) {
			return false;
		}

		return $this->isSystemAdmin($actor) || $this->isTeacher($actor, $workspace);
	}
}
