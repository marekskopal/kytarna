<?php

declare(strict_types=1);

namespace Ukolio\Service\Auth;

use Iterator;
use Ukolio\Model\Entity\Enum\SystemRoleEnum;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workspace;

interface AdminServiceInterface
{
	/** @return Iterator<User> */
	public function listUsers(): Iterator;

	/** @return Iterator<Workspace> */
	public function listWorkspaces(): Iterator;

	public function countMembers(Workspace $workspace): int;

	public function countWorkspacesForUser(User $user): int;

	public function countOwnedWorkspaces(User $user): int;

	/** @return list<Workspace> */
	public function findSoleOwnerWorkspaces(User $user): array;

	public function updateUser(User $actor, User $target, ?string $name, ?string $email, ?SystemRoleEnum $systemRole): User;

	public function deleteUser(User $actor, User $target): void;

	public function deleteWorkspace(User $actor, Workspace $workspace): void;
}
