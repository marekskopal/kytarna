<?php

declare(strict_types=1);

namespace Ukolio\Service\Auth;

use Ukolio\Model\Entity\Enum\SystemRoleEnum;
use Ukolio\Model\Entity\Enum\WorkspaceRoleEnum;
use Ukolio\Model\Entity\TaskComment;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Model\Entity\WorkspaceUser;
use Ukolio\Service\Provider\WorkspaceProviderInterface;

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

	public function canManageProjects(User $user, Workspace $workspace): bool
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

	public function canManageTasks(User $user, Workspace $workspace): bool
	{
		if ($this->isSystemAdmin($user)) {
			return true;
		}

		return $this->workspaceProvider->isMember($user, $workspace);
	}

	public function canManageFields(User $user, Workspace $workspace): bool
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

	public function canManagePriorities(User $user, Workspace $workspace): bool
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

	public function canManageTaskTemplates(User $user, Workspace $workspace): bool
	{
		if ($this->isSystemAdmin($user)) {
			return true;
		}

		// Templates capture task content, not workspace configuration — every member may manage them.
		return $this->workspaceProvider->isMember($user, $workspace);
	}

	public function canManageScripts(User $user, Workspace $workspace): bool
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

	public function canDeleteTaskComment(User $user, Workspace $workspace, TaskComment $comment): bool
	{
		if ($this->isSystemAdmin($user)) {
			return true;
		}

		$membership = $this->workspaceProvider->findMembership($user, $workspace);
		if ($membership === null) {
			return false;
		}

		if ($membership->role === WorkspaceRoleEnum::Owner || $membership->role === WorkspaceRoleEnum::Admin) {
			return true;
		}

		return $comment->author->id === $user->id;
	}

	public function canEditTaskComment(User $user, Workspace $workspace, TaskComment $comment): bool
	{
		if ($this->isSystemAdmin($user)) {
			return true;
		}

		// Editing rewrites someone's words, so — unlike deletion — only the author may do it.
		return $this->workspaceProvider->isMember($user, $workspace) && $comment->author->id === $user->id;
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
