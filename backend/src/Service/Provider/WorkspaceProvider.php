<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use DateTimeImmutable;
use Iterator;
use Kytarna\Model\Entity\Enum\EventTypeEnum;
use Kytarna\Model\Entity\Enum\WorkspaceRoleEnum;
use Kytarna\Model\Entity\User;
use Kytarna\Model\Entity\Workspace;
use Kytarna\Model\Entity\WorkspaceUser;
use Kytarna\Model\Repository\UserRepository;
use Kytarna\Model\Repository\WorkspaceRepository;
use Kytarna\Model\Repository\WorkspaceUserRepository;
use Kytarna\Service\Provider\Exception\WorkspaceOwnershipException;
use Kytarna\Validator\TextFieldValidator;

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

	public function findOwnedWorkspace(User $user): ?Workspace
	{
		foreach ($this->workspaceRepository->findByOwner($user->id) as $workspace) {
			return $workspace;
		}

		return null;
	}

	public function createWorkspace(User $owner, string $name): Workspace
	{
		if ($this->findOwnedWorkspace($owner) !== null) {
			throw new WorkspaceOwnershipException();
		}

		$name = TextFieldValidator::validateName($name, 'Workspace');
		$now = new DateTimeImmutable();
		$workspace = new Workspace(owner: $owner, name: $name, joinCode: $this->generateUniqueJoinCode());
		$workspace->createdAt = $now;
		$workspace->updatedAt = $now;

		$this->workspaceRepository->persist($workspace);

		$this->addMember($workspace, $owner, WorkspaceRoleEnum::Teacher);

		if ($owner->currentWorkspaceId === null) {
			$this->switchCurrentWorkspace($owner, $workspace);
		}

		return $workspace;
	}

	public function updateWorkspace(Workspace $workspace, string $name, ?bool $isPublic = null, ?string $description = null): Workspace
	{
		$workspace->name = TextFieldValidator::validateName($name, 'Workspace');
		if ($isPublic !== null) {
			$workspace->isPublic = $isPublic;
		}
		if ($description !== null) {
			$workspace->description = $description === '' ? null : $description;
		}
		$workspace->updatedAt = new DateTimeImmutable();
		$this->workspaceRepository->persist($workspace);

		return $workspace;
	}

	public function rotateJoinCode(Workspace $workspace): string
	{
		$code = $this->generateUniqueJoinCode();
		$workspace->joinCode = $code;
		$workspace->updatedAt = new DateTimeImmutable();
		$this->workspaceRepository->persist($workspace);

		return $code;
	}

	public function joinAsStudent(User $actor, Workspace $workspace): WorkspaceUser
	{
		$membership = $this->addMember($workspace, $actor, WorkspaceRoleEnum::Student);

		if ($actor->currentWorkspaceId === null) {
			$this->switchCurrentWorkspace($actor, $workspace);
		}

		$this->eventProvider->recordWorkspaceEvent(
			$actor,
			$workspace,
			EventTypeEnum::MemberJoined,
			['userId' => $actor->id, 'userName' => $actor->name],
		);

		return $membership;
	}

	/** @return Iterator<Workspace> */
	public function findPublicWorkspaces(User $user, ?string $search, int $limit, int $offset): Iterator
	{
		$excludeIds = [];
		foreach ($this->getMemberships($user) as $membership) {
			$excludeIds[] = $membership->workspace->id;
		}

		return $this->workspaceRepository->findPublic($search, $limit, $offset, $excludeIds);
	}

	public function findByJoinCode(string $joinCode): ?Workspace
	{
		$joinCode = trim($joinCode);
		if ($joinCode === '') {
			return null;
		}

		return $this->workspaceRepository->findByJoinCode($joinCode);
	}

	private function generateUniqueJoinCode(): string
	{
		do {
			$code = bin2hex(random_bytes(6));
		} while ($this->workspaceRepository->findByJoinCode($code) !== null);

		return $code;
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
