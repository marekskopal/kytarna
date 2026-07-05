<?php

declare(strict_types=1);

namespace Kytario\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use Kytario\Controller\WorkspaceController;
use Kytario\Model\Entity\Enum\WorkspaceRoleEnum;
use Kytario\Model\Repository\StatusRepository;
use Kytario\Model\Repository\WorkflowRepository;
use Kytario\OAuth\AuthorizationServiceInterface;
use Kytario\OAuth\ClientServiceInterface;
use Kytario\Tests\Support\Fixture;
use Kytario\Tests\Support\IntegrationTestCase;

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
		Fixture::addMember($workspace, $admin, WorkspaceRoleEnum::Admin);

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

	public function testTransferOwnership(): void
	{
		$owner = Fixture::createUser(email: 'a@example.com');
		$other = Fixture::createUser(email: 'b@example.com');
		$workspace = Fixture::createWorkspace($owner);
		Fixture::addMember($workspace, $other, WorkspaceRoleEnum::Admin);

		$response = $this->request(
			'POST',
			'/api/workspaces/' . $workspace->id . '/transfer-ownership',
			body: ['userId' => $other->id],
			authenticatedAs: $owner,
		);
		self::assertSame(200, $response->getStatusCode());

		// Members listing now shows other as Owner, original owner as Admin
		$members = $this->request('GET', '/api/workspaces/' . $workspace->id . '/members', authenticatedAs: $other);
		self::assertSame(200, $members->getStatusCode());
		$byEmail = [];
		foreach ($this->jsonList($members) as $member) {
			assert(is_string($member['email']));
			assert(is_string($member['role']));
			$byEmail[$member['email']] = $member['role'];
		}
		self::assertSame('Owner', $byEmail['b@example.com']);
		self::assertSame('Admin', $byEmail['a@example.com']);
	}

	public function testTransferOwnershipRejectsNonOwner(): void
	{
		$owner = Fixture::createUser(email: 'a@example.com');
		$other = Fixture::createUser(email: 'b@example.com');
		$workspace = Fixture::createWorkspace($owner);
		Fixture::addMember($workspace, $other, WorkspaceRoleEnum::Admin);

		$response = $this->request(
			'POST',
			'/api/workspaces/' . $workspace->id . '/transfer-ownership',
			body: ['userId' => $owner->id],
			authenticatedAs: $other,
		);
		self::assertSame(401, $response->getStatusCode());
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

	public function testRemovingMemberUnassignsTheirTasks(): void
	{
		$owner = Fixture::createUser(email: 'owner@example.com');
		$member = Fixture::createUser(email: 'member@example.com');
		$workspace = Fixture::createWorkspace($owner);
		Fixture::addMember($workspace, $member, WorkspaceRoleEnum::Member);
		$project = Fixture::createProject($owner, $workspace);

		$create = $this->request(
			'POST',
			'/api/projects/' . $project->id . '/tasks',
			body: [
				'statusId' => $this->firstStatusId($project->id),
				'name' => 'Member task',
				'description' => null,
				'priority' => 'Medium',
				'assigneeId' => $member->id,
			],
			authenticatedAs: $owner,
		);
		self::assertSame(200, $create->getStatusCode());
		$body = $this->jsonBody($create);
		self::assertSame($member->id, $body['assigneeId']);
		$code = self::stringField($body['code']);

		$remove = $this->request('DELETE', '/api/workspaces/' . $workspace->id . '/members/' . $member->id, authenticatedAs: $owner);
		self::assertSame(200, $remove->getStatusCode());

		$get = $this->request('GET', '/api/tasks/' . $code, authenticatedAs: $owner);
		self::assertSame(200, $get->getStatusCode());
		self::assertNull($this->jsonBody($get)['assigneeId']);
	}

	public function testRevokeMcpClientKillsItsTokens(): void
	{
		$owner = Fixture::createUser(email: 'owner@example.com');
		$member = Fixture::createUser(email: 'member@example.com');
		$workspace = Fixture::createWorkspace($owner);
		Fixture::addMember($workspace, $member, WorkspaceRoleEnum::Member);

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

	private function firstStatusId(int $projectId): int
	{
		$workflowRepo = $this->container->get(WorkflowRepository::class);
		assert($workflowRepo instanceof WorkflowRepository);
		$workflow = $workflowRepo->findByProject($projectId);
		assert($workflow !== null);

		$statusRepo = $this->container->get(StatusRepository::class);
		assert($statusRepo instanceof StatusRepository);
		foreach ($statusRepo->findByWorkflow($workflow->id) as $status) {
			return $status->id;
		}
		self::fail('No statuses found');
	}
}
