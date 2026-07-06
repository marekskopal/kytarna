<?php

declare(strict_types=1);

namespace Kytarna\Tests\Service\Auth;

use ArrayIterator;
use DateTimeImmutable;
use Iterator;
use Kytarna\Model\Entity\Enum\LocaleEnum;
use Kytarna\Model\Entity\Enum\SystemRoleEnum;
use Kytarna\Model\Entity\Enum\WorkspaceRoleEnum;
use Kytarna\Model\Entity\User;
use Kytarna\Model\Entity\Workspace;
use Kytarna\Model\Entity\WorkspaceUser;
use Kytarna\Service\Auth\PermissionChecker;
use Kytarna\Service\Provider\WorkspaceProviderInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

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
		self::assertTrue($checker->canManageCourses($admin, $ws));
		self::assertTrue($checker->canManageLectures($admin, $ws));
		self::assertTrue($checker->canManageSongs($admin, $ws));
		self::assertTrue($checker->canViewWorkspace($admin, $ws));
	}

	public function testTeacherCanManageContentButStudentCannot(): void
	{
		$teacher = $this->makeUser(1);
		$student = $this->makeUser(2);
		$ws = $this->makeWorkspace($teacher);

		$teacherMembership = $this->makeMembership($ws, $teacher, WorkspaceRoleEnum::Teacher);
		$studentMembership = $this->makeMembership($ws, $student, WorkspaceRoleEnum::Student);

		$checker = new PermissionChecker($this->fakeProvider([
			$ws->id => [1 => $teacherMembership, 2 => $studentMembership],
		]));

		// Teacher edits everything.
		self::assertTrue($checker->isTeacher($teacher, $ws));
		self::assertTrue($checker->canManageWorkspace($teacher, $ws));
		self::assertTrue($checker->canManageMembers($teacher, $ws));
		self::assertTrue($checker->canManageCourses($teacher, $ws));
		self::assertTrue($checker->canManageLectures($teacher, $ws));
		self::assertTrue($checker->canManageSongs($teacher, $ws));
		self::assertTrue($checker->canManageTags($teacher, $ws));

		// Student has read-only content, but may view and track their own progress.
		self::assertFalse($checker->isTeacher($student, $ws));
		self::assertFalse($checker->canManageWorkspace($student, $ws));
		self::assertFalse($checker->canManageMembers($student, $ws));
		self::assertFalse($checker->canManageCourses($student, $ws));
		self::assertFalse($checker->canManageLectures($student, $ws));
		self::assertFalse($checker->canManageSongs($student, $ws));
		self::assertFalse($checker->canManageTags($student, $ws));
		self::assertTrue($checker->canViewWorkspace($student, $ws));
		self::assertTrue($checker->canTrackProgress($student, $ws));
	}

	public function testRemoveMemberRules(): void
	{
		$teacher = $this->makeUser(1);
		$student = $this->makeUser(2);
		$ws = $this->makeWorkspace($teacher);

		$teacherM = $this->makeMembership($ws, $teacher, WorkspaceRoleEnum::Teacher);
		$studentM = $this->makeMembership($ws, $student, WorkspaceRoleEnum::Student);

		$checker = new PermissionChecker($this->fakeProvider([
			$ws->id => [1 => $teacherM, 2 => $studentM],
		]));

		// Teacher can remove a student; the teacher cannot be removed.
		self::assertTrue($checker->canRemoveMember($teacher, $ws, $studentM));
		self::assertFalse($checker->canRemoveMember($teacher, $ws, $teacherM));
		// A student may remove themselves (leave) but not the teacher or another student.
		self::assertTrue($checker->canRemoveMember($student, $ws, $studentM));
		self::assertFalse($checker->canRemoveMember($student, $ws, $teacherM));
	}

	public function testInvitableRoleConstraints(): void
	{
		$teacher = $this->makeUser(1);
		$student = $this->makeUser(2);
		$ws = $this->makeWorkspace($teacher);

		$teacherM = $this->makeMembership($ws, $teacher, WorkspaceRoleEnum::Teacher);
		$studentM = $this->makeMembership($ws, $student, WorkspaceRoleEnum::Student);

		$checker = new PermissionChecker($this->fakeProvider([
			$ws->id => [1 => $teacherM, 2 => $studentM],
		]));

		// Only the teacher can invite, and only as a Student.
		self::assertTrue($checker->canInviteAs($teacher, $ws, WorkspaceRoleEnum::Student));
		self::assertFalse($checker->canInviteAs($teacher, $ws, WorkspaceRoleEnum::Teacher));
		self::assertFalse($checker->canInviteAs($student, $ws, WorkspaceRoleEnum::Student));
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

			public function findOwnedWorkspace(User $user): ?Workspace
			{
				return null;
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

			public function updateWorkspace(
				Workspace $workspace,
				string $name,
				?bool $isPublic = null,
				?string $description = null,
			): Workspace
			{
				throw new \RuntimeException('not used');
			}

			public function rotateJoinCode(Workspace $workspace): string
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

			public function joinAsStudent(User $actor, Workspace $workspace): WorkspaceUser
			{
				throw new \RuntimeException('not used');
			}

			public function removeMember(WorkspaceUser $membership): void
			{
				// no-op
			}

			/** @return Iterator<Workspace> */
			public function findPublicWorkspaces(User $user, ?string $search, int $limit, int $offset): Iterator
			{
				return new ArrayIterator([]);
			}

			public function findByJoinCode(string $joinCode): ?Workspace
			{
				return null;
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
