<?php

declare(strict_types=1);

namespace Kytarna\Service\Auth;

use Kytarna\Model\Entity\Enum\SystemRoleEnum;
use Kytarna\Model\Entity\Enum\WorkspaceRoleEnum;
use Kytarna\Model\Entity\User;
use Kytarna\Model\Entity\Workspace;
use Kytarna\Model\Entity\WorkspaceUser;
use Kytarna\Service\Provider\WorkspaceProviderInterface;

final readonly class PermissionChecker implements PermissionCheckerInterface
{
	public function __construct(private WorkspaceProviderInterface $workspaceProvider)
	{
	}

	public function isSystemAdmin(User $user): bool
	{
		return $user->systemRole === SystemRoleEnum::SystemAdmin;
	}

	public function canViewWorkspace(User $user, Workspace $workspace): bool
	{
		if ($this->isSystemAdmin($user)) {
			return true;
		}

		return $this->workspaceProvider->isMember($user, $workspace);
	}

	public function canManageWorkspace(User $user, Workspace $workspace): bool
	{
		if ($this->isSystemAdmin($user)) {
			return true;
		}

		$membership = $this->workspaceProvider->findMembership($user, $workspace);
		return $membership !== null && $membership->role === WorkspaceRoleEnum::Owner;
	}

	public function canManageMembers(User $user, Workspace $workspace): bool
	{
		if ($this->isSystemAdmin($user)) {
			return true;
		}

		$membership = $this->workspaceProvider->findMembership($user, $workspace);
		if ($membership === null) {
			return false;
		}

		return $membership->role === WorkspaceRoleEnum::Owner
			|| $membership->role === WorkspaceRoleEnum::Admin;
	}

	public function canRemoveMember(User $actor, Workspace $workspace, WorkspaceUser $target): bool
	{
		if ($target->user->id === $actor->id) {
			return $target->role !== WorkspaceRoleEnum::Owner;
		}

		if ($this->isSystemAdmin($actor)) {
			return $target->role !== WorkspaceRoleEnum::Owner;
		}

		$actorMembership = $this->workspaceProvider->findMembership($actor, $workspace);
		if ($actorMembership === null) {
			return false;
		}

		if ($actorMembership->role === WorkspaceRoleEnum::Owner) {
			return $target->role !== WorkspaceRoleEnum::Owner;
		}

		if ($actorMembership->role === WorkspaceRoleEnum::Admin) {
			return $target->role === WorkspaceRoleEnum::Member;
		}

		return false;
	}

	public function canChangeRole(User $actor, Workspace $workspace, WorkspaceUser $target, WorkspaceRoleEnum $newRole): bool
	{
		if ($newRole === WorkspaceRoleEnum::Owner || $target->role === WorkspaceRoleEnum::Owner) {
			return false;
		}

		if ($this->isSystemAdmin($actor)) {
			return true;
		}

		$actorMembership = $this->workspaceProvider->findMembership($actor, $workspace);
		if ($actorMembership === null) {
			return false;
		}

		return $actorMembership->role === WorkspaceRoleEnum::Owner
			|| $actorMembership->role === WorkspaceRoleEnum::Admin;
	}

	public function canManageCourses(User $user, Workspace $workspace): bool
	{
		if ($this->isSystemAdmin($user)) {
			return true;
		}

		$membership = $this->workspaceProvider->findMembership($user, $workspace);
		if ($membership === null) {
			return false;
		}

		return $membership->role === WorkspaceRoleEnum::Owner
			|| $membership->role === WorkspaceRoleEnum::Admin;
	}

	public function canManageLectures(User $user, Workspace $workspace): bool
	{
		if ($this->isSystemAdmin($user)) {
			return true;
		}

		return $this->workspaceProvider->isMember($user, $workspace);
	}

	public function canManageTags(User $user, Workspace $workspace): bool
	{
		if ($this->isSystemAdmin($user)) {
			return true;
		}

		$membership = $this->workspaceProvider->findMembership($user, $workspace);
		if ($membership === null) {
			return false;
		}

		return $membership->role === WorkspaceRoleEnum::Owner
			|| $membership->role === WorkspaceRoleEnum::Admin;
	}

	public function canInviteAs(User $actor, Workspace $workspace, WorkspaceRoleEnum $role): bool
	{
		if ($role === WorkspaceRoleEnum::Owner) {
			return false;
		}

		if ($this->isSystemAdmin($actor)) {
			return true;
		}

		$membership = $this->workspaceProvider->findMembership($actor, $workspace);
		if ($membership === null) {
			return false;
		}

		if ($membership->role === WorkspaceRoleEnum::Owner) {
			return true;
		}

		if ($membership->role === WorkspaceRoleEnum::Admin) {
			return $role === WorkspaceRoleEnum::Member;
		}

		return false;
	}
}
