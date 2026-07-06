<?php

declare(strict_types=1);

namespace Kytarna\Tests\Mcp;

use Kytarna\Mcp\McpUserContextInterface;
use Kytarna\Mcp\Tool\MemberTools;
use Kytarna\Model\Entity\Enum\WorkspaceRoleEnum;
use Kytarna\Model\Entity\User;
use Kytarna\Service\Actor\ActorContextInterface;
use Kytarna\Service\Provider\WorkspaceProviderInterface;
use Kytarna\Tests\Support\AppHarness;
use Kytarna\Tests\Support\Fixture;
use Kytarna\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(MemberTools::class)]
final class MemberToolsTest extends IntegrationTestCase
{
	public function testListWorkspaceMembersReturnsAllMembersWithRoles(): void
	{
		$owner = Fixture::createUser(name: 'Owner');
		$workspace = Fixture::createWorkspace($owner);
		$admin = Fixture::createUser(email: 'admin@example.com', name: 'Admin');
		$member = Fixture::createUser(email: 'member@example.com', name: 'Member');
		Fixture::addMember($workspace, $admin, WorkspaceRoleEnum::Student);
		Fixture::addMember($workspace, $member, WorkspaceRoleEnum::Student);

		$tools = $this->bootAs($owner);

		$result = $tools->listWorkspaceMembers();
		self::assertCount(3, $result->members);

		$byEmail = [];
		foreach ($result->members as $dto) {
			$byEmail[$dto->email] = $dto;
		}

		self::assertSame(WorkspaceRoleEnum::Teacher->value, $byEmail[$owner->email]->role);
		self::assertSame(WorkspaceRoleEnum::Student->value, $byEmail['admin@example.com']->role);
		self::assertSame(WorkspaceRoleEnum::Student->value, $byEmail['member@example.com']->role);
	}

	public function testFindMemberByEmailIsCaseInsensitive(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$member = Fixture::createUser(email: 'Marek@Example.com', name: 'Marek');
		Fixture::addMember($workspace, $member, WorkspaceRoleEnum::Student);

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
		Fixture::addMember($workspaceB, $memberB, WorkspaceRoleEnum::Student);

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
		self::assertSame(WorkspaceRoleEnum::Student->value, $invitation->role);
		self::assertNull($invitation->acceptedAt);

		$pdo = AppHarness::pdo();
		$stmt = $pdo->prepare('SELECT COUNT(*) FROM invitations WHERE workspace_id = :id AND email = :email');
		if ($stmt === false) {
			self::fail('Failed to prepare SELECT statement');
		}
		$stmt->execute(['id' => $workspace->id, 'email' => 'newcomer@example.com']);
		self::assertSame(1, (int) $stmt->fetchColumn());
	}

	public function testInviteMemberAsStudentIsRejected(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$member = Fixture::createUser(email: 'member@example.com');
		Fixture::addMember($workspace, $member, WorkspaceRoleEnum::Student);
		$workspaceProvider = AppHarness::container()->get(WorkspaceProviderInterface::class);
		assert($workspaceProvider instanceof WorkspaceProviderInterface);
		$workspaceProvider->switchCurrentWorkspace($member, $workspace);

		$tools = $this->bootAs($member);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessageIsOrContains('do not have permission');
		$tools->inviteMember('someone@example.com');
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
