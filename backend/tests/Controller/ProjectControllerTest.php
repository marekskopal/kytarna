<?php

declare(strict_types=1);

namespace Kytario\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use Kytario\Controller\ProjectController;
use Kytario\Model\Entity\Enum\WorkspaceRoleEnum;
use Kytario\Model\Repository\StatusRepository;
use Kytario\Model\Repository\UserRepository;
use Kytario\Tests\Support\Fixture;
use Kytario\Tests\Support\IntegrationTestCase;

#[CoversClass(ProjectController::class)]
final class ProjectControllerTest extends IntegrationTestCase
{
	public function testOwnerCanCreateProjectAndDefaultWorkflowIsCreated(): void
	{
		$owner = Fixture::createUser();
		Fixture::createWorkspace($owner);

		$response = $this->request(
			'POST',
			'/api/projects',
			body: ['name' => 'My Project', 'description' => 'desc'],
			authenticatedAs: $owner,
		);

		self::assertSame(200, $response->getStatusCode());
		$project = $this->jsonBody($response);
		self::assertSame('My Project', $project['name']);
		self::assertNotEmpty($project['prefix']);
		$projectId = self::intField($project['id']);

		// Project shows up in the list
		$listResponse = $this->request('GET', '/api/projects', authenticatedAs: $owner);
		self::assertSame(200, $listResponse->getStatusCode());
		self::assertCount(1, $this->jsonList($listResponse));

		// Default workflow has 3 statuses
		$workflowResponse = $this->request('GET', '/api/projects/' . $projectId . '/workflow', authenticatedAs: $owner);
		self::assertSame(200, $workflowResponse->getStatusCode());
		$workflow = $this->jsonBody($workflowResponse);
		$workflowId = self::intField($workflow['id']);

		$statusRepo = $this->container->get(StatusRepository::class);
		assert($statusRepo instanceof StatusRepository);
		$statuses = iterator_to_array($statusRepo->findByWorkflow($workflowId), false);
		self::assertCount(3, $statuses);
	}

	public function testMemberCannotCreateProject(): void
	{
		$owner = Fixture::createUser(email: 'owner@example.com');
		$member = Fixture::createUser(email: 'member@example.com');
		$workspace = Fixture::createWorkspace($owner);
		Fixture::addMember($workspace, $member, WorkspaceRoleEnum::Member);
		// Member needs to have this workspace as current. createWorkspace switches owner; member's currentWorkspaceId may be null.
		$member->currentWorkspaceId = $workspace->id;
		$repo = $this->container->get(UserRepository::class);
		assert($repo instanceof UserRepository);
		$repo->persist($member);

		$response = $this->request(
			'POST',
			'/api/projects',
			body: ['name' => 'X', 'description' => null],
			authenticatedAs: $member,
		);

		self::assertSame(401, $response->getStatusCode());
	}

	public function testAdminCanUpdateAndDeleteProject(): void
	{
		$owner = Fixture::createUser(email: 'o@example.com');
		$admin = Fixture::createUser(email: 'a@example.com');
		$workspace = Fixture::createWorkspace($owner);
		Fixture::addMember($workspace, $admin, WorkspaceRoleEnum::Admin);
		$admin->currentWorkspaceId = $workspace->id;
		$repo = $this->container->get(UserRepository::class);
		assert($repo instanceof UserRepository);
		$repo->persist($admin);

		$project = Fixture::createProject($owner, $workspace);

		$update = $this->request(
			'PUT',
			'/api/projects/' . $project->id,
			body: ['name' => 'Renamed', 'description' => null],
			authenticatedAs: $admin,
		);
		self::assertSame(200, $update->getStatusCode());
		self::assertSame('Renamed', $this->jsonBody($update)['name']);

		$delete = $this->request('DELETE', '/api/projects/' . $project->id, authenticatedAs: $admin);
		self::assertSame(200, $delete->getStatusCode());

		$list = $this->request('GET', '/api/projects', authenticatedAs: $admin);
		self::assertCount(0, $this->jsonList($list));
	}

	public function testGetProjectFromAnotherWorkspaceIsNotFound(): void
	{
		$owner = Fixture::createUser(email: 'owner@example.com');
		$workspaceA = Fixture::createWorkspace($owner, 'A');
		$projectInA = Fixture::createProject($owner, $workspaceA);

		$intruder = Fixture::createUser(email: 'intruder@example.com');
		Fixture::createWorkspace($intruder, 'B');

		$response = $this->request('GET', '/api/projects/' . $projectInA->id, authenticatedAs: $intruder);
		self::assertSame(404, $response->getStatusCode());
	}

	public function testUpdateProjectInAnotherWorkspaceIsNotFound(): void
	{
		$owner = Fixture::createUser(email: 'owner@example.com');
		$workspaceA = Fixture::createWorkspace($owner, 'A');
		$projectInA = Fixture::createProject($owner, $workspaceA);

		$intruder = Fixture::createUser(email: 'intruder@example.com');
		Fixture::createWorkspace($intruder, 'B');

		$response = $this->request(
			'PUT',
			'/api/projects/' . $projectInA->id,
			body: ['name' => 'Hijacked', 'description' => null],
			authenticatedAs: $intruder,
		);
		self::assertSame(404, $response->getStatusCode());
	}

	public function testDeleteProjectInAnotherWorkspaceIsNotFound(): void
	{
		$owner = Fixture::createUser(email: 'owner@example.com');
		$workspaceA = Fixture::createWorkspace($owner, 'A');
		$projectInA = Fixture::createProject($owner, $workspaceA);

		$intruder = Fixture::createUser(email: 'intruder@example.com');
		Fixture::createWorkspace($intruder, 'B');

		$response = $this->request('DELETE', '/api/projects/' . $projectInA->id, authenticatedAs: $intruder);
		self::assertSame(404, $response->getStatusCode());
	}

	public function testCreateProjectWithEmptyNameIsRejected(): void
	{
		$owner = Fixture::createUser();
		Fixture::createWorkspace($owner);

		$response = $this->request(
			'POST',
			'/api/projects',
			body: ['name' => '  ', 'description' => null],
			authenticatedAs: $owner,
		);

		self::assertSame(422, $response->getStatusCode());
	}

	public function testCreateProjectWithOverlongNameIsRejected(): void
	{
		$owner = Fixture::createUser();
		Fixture::createWorkspace($owner);

		$response = $this->request(
			'POST',
			'/api/projects',
			body: ['name' => str_repeat('a', 256), 'description' => null],
			authenticatedAs: $owner,
		);

		self::assertSame(422, $response->getStatusCode());
	}
}
