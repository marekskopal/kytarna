<?php

declare(strict_types=1);

namespace Ukolio\Tests\Service\Auth;

use ArrayIterator;
use DateTimeImmutable;
use Iterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Ukolio\Model\Entity\Enum\LocaleEnum;
use Ukolio\Model\Entity\Enum\SystemRoleEnum;
use Ukolio\Model\Entity\Enum\WorkspaceRoleEnum;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Model\Entity\WorkspaceUser;
use Ukolio\Service\Auth\PermissionChecker;
use Ukolio\Service\Provider\WorkspaceProviderInterface;

#[CoversClass(PermissionChecker::class)]
final class PermissionCheckerTest extends TestCase
{
	public function testSystemAdminCanManageAnyWorkspace(): void
	{
		$admin = $this->makeUser(1, SystemRoleEnum::SystemAdmin);
		$owner = $this->makeUser(2);
		$ws = $this->makeWorkspace($owner);

		$checker = new PermissionChecker($this->fakeProvider([$ws->id => []]));

		self::assertTrue($checker->isSystemAdmin($admin));
		self::assertTrue($checker->canManageWorkspace($admin, $ws));
		self::assertTrue($checker->canManageMembers($admin, $ws));
		self::assertTrue($checker->canManageProjects($admin, $ws));
		self::assertTrue($checker->canViewWorkspace($admin, $ws));
	}

	public function testOwnerCanManageWorkspaceButMemberCannot(): void
	{
		$owner = $this->makeUser(1);
		$member = $this->makeUser(2);
		$ws = $this->makeWorkspace($owner);

		$ownerMembership = $this->makeMembership($ws, $owner, WorkspaceRoleEnum::Owner);
		$memberMembership = $this->makeMembership($ws, $member, WorkspaceRoleEnum::Member);

		$checker = new PermissionChecker($this->fakeProvider([
			$ws->id => [1 => $ownerMembership, 2 => $memberMembership],
		]));

		self::assertTrue($checker->canManageWorkspace($owner, $ws));
		self::assertFalse($checker->canManageWorkspace($member, $ws));
		self::assertFalse($checker->canManageProjects($member, $ws));
		self::assertTrue($checker->canManageTasks($member, $ws));
	}

	public function testAdminCanManageMembersButNotWorkspaceItself(): void
	{
		$owner = $this->makeUser(1);
		$admin = $this->makeUser(2);
		$ws = $this->makeWorkspace($owner);

		$ownerMembership = $this->makeMembership($ws, $owner, WorkspaceRoleEnum::Owner);
		$adminMembership = $this->makeMembership($ws, $admin, WorkspaceRoleEnum::Admin);

		$checker = new PermissionChecker($this->fakeProvider([
			$ws->id => [1 => $ownerMembership, 2 => $adminMembership],
		]));

		self::assertFalse($checker->canManageWorkspace($admin, $ws));
		self::assertTrue($checker->canManageMembers($admin, $ws));
		self::assertTrue($checker->canManageProjects($admin, $ws));
	}

	public function testAdminCannotRemoveOwnerOrAnotherAdmin(): void
	{
		$owner = $this->makeUser(1);
		$admin = $this->makeUser(2);
		$otherAdmin = $this->makeUser(3);
		$member = $this->makeUser(4);
		$ws = $this->makeWorkspace($owner);

		$ownerM = $this->makeMembership($ws, $owner, WorkspaceRoleEnum::Owner);
		$adminM = $this->makeMembership($ws, $admin, WorkspaceRoleEnum::Admin);
		$otherAdminM = $this->makeMembership($ws, $otherAdmin, WorkspaceRoleEnum::Admin);
		$memberM = $this->makeMembership($ws, $member, WorkspaceRoleEnum::Member);

		$checker = new PermissionChecker($this->fakeProvider([
			$ws->id => [1 => $ownerM, 2 => $adminM, 3 => $otherAdminM, 4 => $memberM],
		]));

		self::assertFalse($checker->canRemoveMember($admin, $ws, $ownerM));
		self::assertFalse($checker->canRemoveMember($admin, $ws, $otherAdminM));
		self::assertTrue($checker->canRemoveMember($admin, $ws, $memberM));
		self::assertTrue($checker->canRemoveMember($owner, $ws, $adminM));
	}

	public function testOwnerCannotSelfRemove(): void
	{
		$owner = $this->makeUser(1);
		$ws = $this->makeWorkspace($owner);
		$ownerM = $this->makeMembership($ws, $owner, WorkspaceRoleEnum::Owner);

		$checker = new PermissionChecker($this->fakeProvider([$ws->id => [1 => $ownerM]]));

		self::assertFalse($checker->canRemoveMember($owner, $ws, $ownerM));
	}

	public function testCanChangeRoleMatrix(): void
	{
		$owner = $this->makeUser(1);
		$admin = $this->makeUser(2);
		$member = $this->makeUser(3);
		$ws = $this->makeWorkspace($owner);

		$ownerM = $this->makeMembership($ws, $owner, WorkspaceRoleEnum::Owner);
		$adminM = $this->makeMembership($ws, $admin, WorkspaceRoleEnum::Admin);
		$memberM = $this->makeMembership($ws, $member, WorkspaceRoleEnum::Member);

		$checker = new PermissionChecker($this->fakeProvider([
			$ws->id => [1 => $ownerM, 2 => $adminM, 3 => $memberM],
		]));

		self::assertTrue($checker->canChangeRole($owner, $ws, $memberM, WorkspaceRoleEnum::Admin));
		self::assertTrue($checker->canChangeRole($admin, $ws, $memberM, WorkspaceRoleEnum::Admin));
		self::assertFalse($checker->canChangeRole($member, $ws, $adminM, WorkspaceRoleEnum::Member));
		self::assertFalse($checker->canChangeRole($owner, $ws, $memberM, WorkspaceRoleEnum::Owner));
		self::assertFalse($checker->canChangeRole($admin, $ws, $ownerM, WorkspaceRoleEnum::Admin));
	}

	public function testCanManageFieldsRequiresOwnerOrAdmin(): void
	{
		$owner = $this->makeUser(1);
		$admin = $this->makeUser(2);
		$member = $this->makeUser(3);
		$outsider = $this->makeUser(4);
		$ws = $this->makeWorkspace($owner);

		$ownerM = $this->makeMembership($ws, $owner, WorkspaceRoleEnum::Owner);
		$adminM = $this->makeMembership($ws, $admin, WorkspaceRoleEnum::Admin);
		$memberM = $this->makeMembership($ws, $member, WorkspaceRoleEnum::Member);

		$checker = new PermissionChecker($this->fakeProvider([
			$ws->id => [1 => $ownerM, 2 => $adminM, 3 => $memberM],
		]));

		self::assertTrue($checker->canManageFields($owner, $ws));
		self::assertTrue($checker->canManageFields($admin, $ws));
		self::assertFalse($checker->canManageFields($member, $ws));
		self::assertFalse($checker->canManageFields($outsider, $ws));
	}

	public function testInvitableRoleConstraints(): void
	{
		$owner = $this->makeUser(1);
		$admin = $this->makeUser(2);
		$ws = $this->makeWorkspace($owner);

		$ownerM = $this->makeMembership($ws, $owner, WorkspaceRoleEnum::Owner);
		$adminM = $this->makeMembership($ws, $admin, WorkspaceRoleEnum::Admin);

		$checker = new PermissionChecker($this->fakeProvider([
			$ws->id => [1 => $ownerM, 2 => $adminM],
		]));

		self::assertTrue($checker->canInviteAs($owner, $ws, WorkspaceRoleEnum::Admin));
		self::assertTrue($checker->canInviteAs($owner, $ws, WorkspaceRoleEnum::Member));
		self::assertFalse($checker->canInviteAs($owner, $ws, WorkspaceRoleEnum::Owner));
		self::assertFalse($checker->canInviteAs($admin, $ws, WorkspaceRoleEnum::Admin));
		self::assertTrue($checker->canInviteAs($admin, $ws, WorkspaceRoleEnum::Member));
	}

	private function makeUser(int $id, SystemRoleEnum $systemRole = SystemRoleEnum::User): User
	{
		$user = new User(
			email: sprintf('u%d@example.com', $id),
			password: 'x',
			name: sprintf('User %d', $id),
			locale: LocaleEnum::En,
			currentWorkspaceId: null,
			systemRole: $systemRole,
		);
		$user->id = $id;
		$user->createdAt = new DateTimeImmutable();
		$user->updatedAt = new DateTimeImmutable();
		return $user;
	}

	private function makeWorkspace(User $owner, int $id = 100): Workspace
	{
		$ws = new Workspace(owner: $owner, name: 'WS');
		$ws->id = $id;
		$ws->createdAt = new DateTimeImmutable();
		$ws->updatedAt = new DateTimeImmutable();
		return $ws;
	}

	private function makeMembership(Workspace $ws, User $user, WorkspaceRoleEnum $role): WorkspaceUser
	{
		$m = new WorkspaceUser(workspace: $ws, user: $user, role: $role);
		$m->id = $user->id * 1000 + $ws->id;
		$m->createdAt = new DateTimeImmutable();
		$m->updatedAt = new DateTimeImmutable();
		return $m;
	}

	/** @param array<int, array<int, WorkspaceUser>> $memberships workspace_id -> user_id -> membership */
	private function fakeProvider(array $memberships): WorkspaceProviderInterface
	{
		return new class ($memberships) implements WorkspaceProviderInterface {
			/** @param array<int, array<int, WorkspaceUser>> $memberships */
			public function __construct(private array $memberships)
			{
			}

			public function findMembership(User $user, Workspace $workspace): ?WorkspaceUser
			{
				return $this->memberships[$workspace->id][$user->id] ?? null;
			}

			public function isMember(User $user, Workspace $workspace): bool
			{
				return $this->findMembership($user, $workspace) !== null;
			}

			public function getWorkspace(int $workspaceId): ?Workspace
			{
				return null;
			}

			/** @return Iterator<WorkspaceUser> */
			public function getMemberships(User $user): Iterator
			{
				return new ArrayIterator([]);
			}

			/** @return Iterator<WorkspaceUser> */
			public function getMembers(Workspace $workspace): Iterator
			{
				return new ArrayIterator(array_values($this->memberships[$workspace->id] ?? []));
			}

			public function createWorkspace(User $owner, string $name): Workspace
			{
				throw new \RuntimeException('not used');
			}

			public function updateWorkspace(Workspace $workspace, string $name): Workspace
			{
				throw new \RuntimeException('not used');
			}

			public function deleteWorkspace(Workspace $workspace): void
			{
				// no-op
			}

			public function addMember(Workspace $workspace, User $user, WorkspaceRoleEnum $role): WorkspaceUser
			{
				throw new \RuntimeException('not used');
			}

			public function removeMember(WorkspaceUser $membership): void
			{
				// no-op
			}

			public function changeMemberRole(User $actor, WorkspaceUser $membership, WorkspaceRoleEnum $newRole): WorkspaceUser
			{
				throw new \RuntimeException('not used');
			}

			public function transferOwnership(User $actor, Workspace $workspace, WorkspaceUser $newOwnerMembership): void
			{
				// no-op
			}

			public function switchCurrentWorkspace(User $user, Workspace $workspace): void
			{
				// no-op
			}

			public function getCurrentWorkspace(User $user): ?Workspace
			{
				return null;
			}
		};
	}
}
