<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use Iterator;
use Kytarna\Model\Entity\Enum\WorkspaceRoleEnum;
use Kytarna\Model\Entity\User;
use Kytarna\Model\Entity\Workspace;
use Kytarna\Model\Entity\WorkspaceUser;

interface WorkspaceProviderInterface
{
	public function getWorkspace(int $workspaceId): ?Workspace;

	/** @return Iterator<WorkspaceUser> */
	public function getMemberships(User $user): Iterator;

	/** @return Iterator<WorkspaceUser> */
	public function getMembers(Workspace $workspace): Iterator;

	public function findMembership(User $user, Workspace $workspace): ?WorkspaceUser;

	public function isMember(User $user, Workspace $workspace): bool;

	/** The one workspace the user owns (is Teacher of), or null. Each user owns at most one. */
	public function findOwnedWorkspace(User $user): ?Workspace;

	public function createWorkspace(User $owner, string $name): Workspace;

	public function updateWorkspace(Workspace $workspace, string $name, ?bool $isPublic = null, ?string $description = null): Workspace;

	public function rotateJoinCode(Workspace $workspace): string;

	public function deleteWorkspace(Workspace $workspace): void;

	public function addMember(Workspace $workspace, User $user, WorkspaceRoleEnum $role): WorkspaceUser;

	/** Self-join a workspace as a Student (directory / join-code / accepted invitation). */
	public function joinAsStudent(User $actor, Workspace $workspace): WorkspaceUser;

	public function removeMember(WorkspaceUser $membership): void;

	/**
	 * Public teacher directory, excluding workspaces the user already belongs to.
	 *
	 * @return Iterator<Workspace>
	 */
	public function findPublicWorkspaces(User $user, ?string $search, int $limit, int $offset): Iterator;

	public function findByJoinCode(string $joinCode): ?Workspace;

	public function switchCurrentWorkspace(User $user, Workspace $workspace): void;

	public function getCurrentWorkspace(User $user): ?Workspace;
}
