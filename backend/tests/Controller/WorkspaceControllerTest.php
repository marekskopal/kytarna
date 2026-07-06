<?php

declare(strict_types=1);

namespace Kytarna\Tests\Controller;

use Kytarna\Controller\WorkspaceController;
use Kytarna\Model\Entity\Enum\WorkspaceRoleEnum;
use Kytarna\OAuth\AuthorizationServiceInterface;
use Kytarna\OAuth\ClientServiceInterface;
use Kytarna\Tests\Support\Fixture;
use Kytarna\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(WorkspaceController::class)]
final class WorkspaceControllerTest extends IntegrationTestCase
{
	public function testListReturnsOnlyMembershipsOfUser(): void
	{
		$user = Fixture::createUser();
		Fixture::createWorkspace($user, 'Mine');

		$other = Fixture::createUser(email: 'other@example.com');
		Fixture::createWorkspace($other, 'Theirs');

		$response = $this->request('GET', '/api/workspaces', authenticatedAs: $user);
		self::assertSame(200, $response->getStatusCode());
		$list = $this->jsonList($response);
		self::assertCount(1, $list);
		self::assertSame('Mine', $list[0]['name']);
	}

	public function testCreateWorkspace(): void
	{
		$user = Fixture::createUser();

		$response = $this->request('POST', '/api/workspaces', body: ['name' => 'Brand new'], authenticatedAs: $user);
		self::assertSame(200, $response->getStatusCode());
		self::assertSame('Brand new', $this->jsonBody($response)['name']);
	}

	public function testCreateWorkspaceRejectsEmptyName(): void
	{
		$user = Fixture::createUser();

		$response = $this->request('POST', '/api/workspaces', body: ['name' => '   '], authenticatedAs: $user);
		self::assertSame(422, $response->getStatusCode());
	}

	public function testOnlyOwnerCanRenameWorkspace(): void
	{
		$owner = Fixture::createUser(email: 'owner@example.com');
		$admin = Fixture::createUser(email: 'admin@example.com');
		$workspace = Fixture::createWorkspace($owner, 'Original');
		Fixture::addMember($workspace, $admin, WorkspaceRoleEnum::Student);

		$denied = $this->request(
			'PUT',
			'/api/workspaces/' . $workspace->id,
			body: ['name' => 'New'],
			authenticatedAs: $admin,
		);
		self::assertSame(401, $denied->getStatusCode());

		$ok = $this->request(
			'PUT',
			'/api/workspaces/' . $workspace->id,
			body: ['name' => 'New'],
			authenticatedAs: $owner,
		);
		self::assertSame(200, $ok->getStatusCode());
		self::assertSame('New', $this->jsonBody($ok)['name']);
	}

	public function testTeacherCanOwnOnlyOneWorkspace(): void
	{
		$user = Fixture::createUser();
		Fixture::createWorkspace($user, 'First');

		$response = $this->request('POST', '/api/workspaces', body: ['name' => 'Second'], authenticatedAs: $user);
		self::assertSame(422, $response->getStatusCode());
	}

	public function testStudentJoinsPublicWorkspace(): void
	{
		$teacher = Fixture::createUser(email: 'teacher@example.com');
		$workspace = Fixture::createWorkspace($teacher, 'Studio');

		// Teacher makes the workspace public.
		$this->request('PUT', '/api/workspaces/' . $workspace->id, body: ['isPublic' => true], authenticatedAs: $teacher);

		$student = Fixture::createUser(email: 'student@example.com');
		$join = $this->request('POST', '/api/workspaces/' . $workspace->id . '/join', authenticatedAs: $student);
		self::assertSame(200, $join->getStatusCode());

		// The student now sees the workspace in their memberships as a member.
		$list = $this->jsonList($this->request('GET', '/api/workspaces', authenticatedAs: $student));
		self::assertCount(1, $list);
		self::assertSame('Studio', $list[0]['name']);
	}

	public function testDiscoverListsPublicWorkspacesOnly(): void
	{
		$teacher = Fixture::createUser(email: 'teacher@example.com');
		$public = Fixture::createWorkspace($teacher, 'Public Studio');
		$this->request('PUT', '/api/workspaces/' . $public->id, body: ['isPublic' => true], authenticatedAs: $teacher);

		$privateTeacher = Fixture::createUser(email: 'private@example.com');
		Fixture::createWorkspace($privateTeacher, 'Private Studio');

		$student = Fixture::createUser(email: 'student@example.com');
		$discover = $this->request('GET', '/api/workspaces/discover', authenticatedAs: $student);
		self::assertSame(200, $discover->getStatusCode());
		$names = [];
		foreach ($this->jsonList($discover) as $w) {
			assert(is_string($w['name']));
			$names[] = $w['name'];
		}
		self::assertContains('Public Studio', $names);
		self::assertNotContains('Private Studio', $names);
	}

	public function testStudentJoinsByCode(): void
	{
		$teacher = Fixture::createUser(email: 'teacher@example.com');
		$workspace = Fixture::createWorkspace($teacher, 'Studio');
		self::assertNotNull($workspace->joinCode);

		$student = Fixture::createUser(email: 'student@example.com');
		$join = $this->request('POST', '/api/workspaces/join', body: ['code' => $workspace->joinCode], authenticatedAs: $student);
		self::assertSame(200, $join->getStatusCode());
		self::assertSame('Studio', $this->jsonBody($join)['name']);
	}

	public function testNonMemberCannotViewMembers(): void
	{
		$owner = Fixture::createUser(email: 'owner@example.com');
		$intruder = Fixture::createUser(email: 'intruder@example.com');
		$workspace = Fixture::createWorkspace($owner);

		$response = $this->request('GET', '/api/workspaces/' . $workspace->id . '/members', authenticatedAs: $intruder);
		self::assertSame(401, $response->getStatusCode());
	}

	public function testCannotRemoveOwnerDirectly(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);

		$response = $this->request('DELETE', '/api/workspaces/' . $workspace->id . '/members/' . $owner->id, authenticatedAs: $owner);
		self::assertSame(422, $response->getStatusCode());
	}

	public function testRemovingMemberRevokesTheirAccess(): void
	{
		$owner = Fixture::createUser(email: 'owner@example.com');
		$member = Fixture::createUser(email: 'member@example.com');
		$workspace = Fixture::createWorkspace($owner);
		Fixture::addMember($workspace, $member, WorkspaceRoleEnum::Student);

		$remove = $this->request('DELETE', '/api/workspaces/' . $workspace->id . '/members/' . $member->id, authenticatedAs: $owner);
		self::assertSame(200, $remove->getStatusCode());

		$list = $this->request('GET', '/api/workspaces/' . $workspace->id . '/members', authenticatedAs: $member);
		self::assertSame(401, $list->getStatusCode());
	}

	public function testRevokeMcpClientKillsItsTokens(): void
	{
		$owner = Fixture::createUser(email: 'owner@example.com');
		$member = Fixture::createUser(email: 'member@example.com');
		$workspace = Fixture::createWorkspace($owner);
		Fixture::addMember($workspace, $member, WorkspaceRoleEnum::Student);

		$clientService = $this->container->get(ClientServiceInterface::class);
		assert($clientService instanceof ClientServiceInterface);
		$client = $clientService->registerClient('Rogue Agent', ['http://localhost/cb']);

		$authService = $this->container->get(AuthorizationServiceInterface::class);
		assert($authService instanceof AuthorizationServiceInterface);

		$verifier = 'verifier-revoke-endpoint';
		$challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
		$code = $authService->createAuthorizationCode($client->clientId, $owner->id, $challenge, 'S256', 'http://localhost/cb');
		$pair = $authService->exchangeCode($code, $verifier, $client->clientId, 'http://localhost/cb');

		// A plain member may not revoke.
		$forbidden = $this->request(
			'POST',
			'/api/workspaces/' . $workspace->id . '/mcp-clients/' . $client->clientId . '/revoke',
			authenticatedAs: $member,
		);
		self::assertSame(401, $forbidden->getStatusCode());

		$response = $this->request(
			'POST',
			'/api/workspaces/' . $workspace->id . '/mcp-clients/' . $client->clientId . '/revoke',
			authenticatedAs: $owner,
		);
		self::assertSame(200, $response->getStatusCode());
		self::assertGreaterThan(0, $this->jsonBody($response)['revokedTokens']);

		try {
			$authService->validateAccessToken($pair->accessToken);
			self::fail('Expected the access token to be revoked.');
		} catch (\RuntimeException $e) {
			self::assertSame('Access token has been revoked', $e->getMessage());
		}
	}
}
