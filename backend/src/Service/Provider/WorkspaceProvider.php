<?php

declare(strict_types=1);

namespace Kytario\Service\Provider;

use DateTimeImmutable;
use Iterator;
use Kytario\Model\Entity\Enum\EventTypeEnum;
use Kytario\Model\Entity\Enum\WorkspaceRoleEnum;
use Kytario\Model\Entity\User;
use Kytario\Model\Entity\Workspace;
use Kytario\Model\Entity\WorkspaceUser;
use Kytario\Model\Repository\UserRepository;
use Kytario\Model\Repository\WorkspaceRepository;
use Kytario\Model\Repository\WorkspaceUserRepository;
use Kytario\Validator\TextFieldValidator;
use RuntimeException;

final readonly class WorkspaceProvider implements WorkspaceProviderInterface
{
	public function __construct(
		private WorkspaceRepository $workspaceRepository,
		private WorkspaceUserRepository $workspaceUserRepository,
		private UserRepository $userRepository,
		private EventProviderInterface $eventProvider,
	) {
	}

	public function getWorkspace(int $workspaceId): ?Workspace
	{
		return $this->workspaceRepository->findWorkspaceById($workspaceId);
	}

	/** @return Iterator<WorkspaceUser> */
	public function getMemberships(User $user): Iterator
	{
		return $this->workspaceUserRepository->findByUser($user->id);
	}

	/** @return Iterator<WorkspaceUser> */
	public function getMembers(Workspace $workspace): Iterator
	{
		return $this->workspaceUserRepository->findByWorkspace($workspace->id);
	}

	public function findMembership(User $user, Workspace $workspace): ?WorkspaceUser
	{
		return $this->workspaceUserRepository->findMembership($user->id, $workspace->id);
	}

	public function isMember(User $user, Workspace $workspace): bool
	{
		return $this->findMembership($user, $workspace) !== null;
	}

	public function createWorkspace(User $owner, string $name): Workspace
	{
		$name = TextFieldValidator::validateName($name, 'Workspace');
		$now = new DateTimeImmutable();
		$workspace = new Workspace(owner: $owner, name: $name);
		$workspace->createdAt = $now;
		$workspace->updatedAt = $now;

		$this->workspaceRepository->persist($workspace);

		$this->addMember($workspace, $owner, WorkspaceRoleEnum::Owner);

		if ($owner->currentWorkspaceId === null) {
			$this->switchCurrentWorkspace($owner, $workspace);
		}

		return $workspace;
	}

	public function updateWorkspace(Workspace $workspace, string $name): Workspace
	{
		$name = TextFieldValidator::validateName($name, 'Workspace');
		$workspace->name = $name;
		$workspace->updatedAt = new DateTimeImmutable();
		$this->workspaceRepository->persist($workspace);

		return $workspace;
	}

	public function deleteWorkspace(Workspace $workspace): void
	{
		$this->workspaceRepository->delete($workspace);
	}

	public function addMember(Workspace $workspace, User $user, WorkspaceRoleEnum $role): WorkspaceUser
	{
		$existing = $this->workspaceUserRepository->findMembership($user->id, $workspace->id);
		if ($existing !== null) {
			return $existing;
		}

		$now = new DateTimeImmutable();
		$membership = new WorkspaceUser(workspace: $workspace, user: $user, role: $role);
		$membership->createdAt = $now;
		$membership->updatedAt = $now;

		$this->workspaceUserRepository->persist($membership);

		return $membership;
	}

	public function removeMember(WorkspaceUser $membership): void
	{
		$this->workspaceUserRepository->delete($membership);
	}

	public function changeMemberRole(User $actor, WorkspaceUser $membership, WorkspaceRoleEnum $newRole): WorkspaceUser
	{
		if ($newRole === WorkspaceRoleEnum::Owner) {
			throw new RuntimeException('Use transferOwnership() to assign the Owner role.');
		}
		if ($membership->role === WorkspaceRoleEnum::Owner) {
			throw new RuntimeException('Cannot change the Owner\'s role directly.');
		}

		$previous = $membership->role;
		$membership->role = $newRole;
		$membership->updatedAt = new DateTimeImmutable();
		$this->workspaceUserRepository->persist($membership);

		$this->eventProvider->recordWorkspaceEvent(
			$actor,
			$membership->workspace,
			EventTypeEnum::MemberRoleChanged,
			[
				'userId' => $membership->user->id,
				'userName' => $membership->user->name,
				'fromRole' => $previous->value,
				'toRole' => $newRole->value,
			],
		);

		return $membership;
	}

	public function transferOwnership(User $actor, Workspace $workspace, WorkspaceUser $newOwnerMembership): void
	{
		if ($newOwnerMembership->workspace->id !== $workspace->id) {
			throw new RuntimeException('Target membership belongs to a different workspace.');
		}
		if ($newOwnerMembership->user->id === $workspace->owner->id) {
			throw new RuntimeException('Target user is already the owner.');
		}

		$previousOwner = $workspace->owner;
		$previousMembership = $this->findMembership($previousOwner, $workspace);

		$now = new DateTimeImmutable();

		if ($previousMembership !== null) {
			$previousMembership->role = WorkspaceRoleEnum::Admin;
			$previousMembership->updatedAt = $now;
			$this->workspaceUserRepository->persist($previousMembership);
		}

		$newOwnerMembership->role = WorkspaceRoleEnum::Owner;
		$newOwnerMembership->updatedAt = $now;
		$this->workspaceUserRepository->persist($newOwnerMembership);

		$workspace->owner = $newOwnerMembership->user;
		$workspace->updatedAt = $now;
		$this->workspaceRepository->persist($workspace);

		$this->eventProvider->recordWorkspaceEvent(
			$actor,
			$workspace,
			EventTypeEnum::OwnershipTransferred,
			[
				'fromUserId' => $previousOwner->id,
				'fromUserName' => $previousOwner->name,
				'toUserId' => $newOwnerMembership->user->id,
				'toUserName' => $newOwnerMembership->user->name,
			],
		);
	}

	public function switchCurrentWorkspace(User $user, Workspace $workspace): void
	{
		$user->currentWorkspaceId = $workspace->id;
		$user->updatedAt = new DateTimeImmutable();
		$this->userRepository->persist($user);
	}

	public function getCurrentWorkspace(User $user): ?Workspace
	{
		if ($user->currentWorkspaceId !== null) {
			$workspace = $this->workspaceRepository->findWorkspaceById($user->currentWorkspaceId);
			if ($workspace !== null && $this->isMember($user, $workspace)) {
				return $workspace;
			}
		}

		foreach ($this->getMemberships($user) as $membership) {
			return $membership->workspace;
		}

		return null;
	}
}
