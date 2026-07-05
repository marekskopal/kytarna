<?php

declare(strict_types=1);

namespace Ukolio\Tests\Mcp;

use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Ukolio\Mcp\McpUserContextInterface;
use Ukolio\Mcp\Tool\MemberTools;
use Ukolio\Model\Entity\Enum\WorkspaceRoleEnum;
use Ukolio\Model\Entity\User;
use Ukolio\Service\Actor\ActorContextInterface;
use Ukolio\Service\Provider\WorkspaceProviderInterface;
use Ukolio\Tests\Support\AppHarness;
use Ukolio\Tests\Support\Fixture;
use Ukolio\Tests\Support\IntegrationTestCase;

#[CoversClass(MemberTools::class)]
final class MemberToolsTest extends IntegrationTestCase
{
	public function testListWorkspaceMembersReturnsAllMembersWithRoles(): void
	{
		$owner = Fixture::createUser(name: 'Owner');
		$workspace = Fixture::createWorkspace($owner);
		$admin = Fixture::createUser(email: 'admin@example.com', name: 'Admin');
		$member = Fixture::createUser(email: 'member@example.com', name: 'Member');
		Fixture::addMember($workspace, $admin, WorkspaceRoleEnum::Admin);
		Fixture::addMember($workspace, $member, WorkspaceRoleEnum::Member);

		$tools = $this->bootAs($owner);

		$result = $tools->listWorkspaceMembers();
		self::assertCount(3, $result->members);

		$byEmail = [];
		foreach ($result->members as $dto) {
			$byEmail[$dto->email] = $dto;
		}

		self::assertSame(WorkspaceRoleEnum::Owner->value, $byEmail[$owner->email]->role);
		self::assertSame(WorkspaceRoleEnum::Admin->value, $byEmail['admin@example.com']->role);
		self::assertSame(WorkspaceRoleEnum::Member->value, $byEmail['member@example.com']->role);
	}

	public function testFindMemberByEmailIsCaseInsensitive(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$member = Fixture::createUser(email: 'Marek@Example.com', name: 'Marek');
		Fixture::addMember($workspace, $member, WorkspaceRoleEnum::Member);

		$tools = $this->bootAs($owner);

		$found = $tools->findMemberByEmail('MAREK@example.com');
		self::assertNotNull($found);
		self::assertSame($member->id, $found->userId);

		self::assertNull($tools->findMemberByEmail('nobody@example.com'));
	}

	public function testListAndFindAreScopedToCurrentWorkspace(): void
	{
		$ownerA = Fixture::createUser(name: 'OwnerA');
		Fixture::createWorkspace($ownerA, 'Workspace A');

		$ownerB = Fixture::createUser(email: 'ownerb@example.com', name: 'OwnerB');
		$workspaceB = Fixture::createWorkspace($ownerB, 'Workspace B');
		$memberB = Fixture::createUser(email: 'b-member@example.com', name: 'MemberB');
		Fixture::addMember($workspaceB, $memberB, WorkspaceRoleEnum::Member);

		// ownerA's current workspace is Workspace A — should see only its members.
		$tools = $this->bootAs($ownerA);
		$result = $tools->listWorkspaceMembers();
		self::assertCount(1, $result->members);
		self::assertSame($ownerA->id, $result->members[0]->userId);

		// findMemberByEmail must not leak across workspaces.
		self::assertNull($tools->findMemberByEmail('b-member@example.com'));
	}

	public function testInviteMemberAsOwnerSucceeds(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);

		$tools = $this->bootAs($owner);

		$invitation = $tools->inviteMember('newcomer@example.com');

		self::assertSame('newcomer@example.com', $invitation->email);
		self::assertSame(WorkspaceRoleEnum::Member->value, $invitation->role);
		self::assertNull($invitation->acceptedAt);

		$pdo = AppHarness::pdo();
		$stmt = $pdo->prepare('SELECT COUNT(*) FROM invitations WHERE workspace_id = :id AND email = :email');
		if ($stmt === false) {
			self::fail('Failed to prepare SELECT statement');
		}
		$stmt->execute(['id' => $workspace->id, 'email' => 'newcomer@example.com']);
		self::assertSame(1, (int) $stmt->fetchColumn());
	}

	public function testInviteMemberAsAdminCannotInviteAdmin(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$admin = Fixture::createUser(email: 'admin@example.com');
		Fixture::addMember($workspace, $admin, WorkspaceRoleEnum::Admin);
		$workspaceProvider = AppHarness::container()->get(WorkspaceProviderInterface::class);
		assert($workspaceProvider instanceof WorkspaceProviderInterface);
		$workspaceProvider->switchCurrentWorkspace($admin, $workspace);

		$tools = $this->bootAs($admin);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageIsOrContains('cannot invite a member with this role');
		$tools->inviteMember('another-admin@example.com', 'Admin');
	}

	public function testInviteMemberAsMemberIsRejected(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$member = Fixture::createUser(email: 'member@example.com');
		Fixture::addMember($workspace, $member, WorkspaceRoleEnum::Member);
		$workspaceProvider = AppHarness::container()->get(WorkspaceProviderInterface::class);
		assert($workspaceProvider instanceof WorkspaceProviderInterface);
		$workspaceProvider->switchCurrentWorkspace($member, $workspace);

		$tools = $this->bootAs($member);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageIsOrContains('do not have permission');
		$tools->inviteMember('someone@example.com');
	}

	public function testInviteMemberAsAdminCanInviteMember(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$admin = Fixture::createUser(email: 'admin@example.com');
		Fixture::addMember($workspace, $admin, WorkspaceRoleEnum::Admin);
		$workspaceProvider = AppHarness::container()->get(WorkspaceProviderInterface::class);
		assert($workspaceProvider instanceof WorkspaceProviderInterface);
		$workspaceProvider->switchCurrentWorkspace($admin, $workspace);

		$tools = $this->bootAs($admin);

		$invitation = $tools->inviteMember('newbie@example.com');
		self::assertSame(WorkspaceRoleEnum::Member->value, $invitation->role);
	}

	private function bootAs(User $user): MemberTools
	{
		$ctx = AppHarness::container()->get(McpUserContextInterface::class);
		assert($ctx instanceof McpUserContextInterface);
		$ctx->setUser($user);

		$actor = AppHarness::container()->get(ActorContextInterface::class);
		assert($actor instanceof ActorContextInterface);
		$actor->setAgent('cli', 'Test CLI');

		$tools = AppHarness::container()->get(MemberTools::class);
		assert($tools instanceof MemberTools);
		return $tools;
	}
}
