<?php

declare(strict_types=1);

namespace Kytarna\Tests\Controller;

use Kytarna\Controller\CourseController;
use Kytarna\Model\Entity\Enum\WorkspaceRoleEnum;
use Kytarna\Model\Repository\StatusRepository;
use Kytarna\Model\Repository\UserRepository;
use Kytarna\Tests\Support\Fixture;
use Kytarna\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CourseController::class)]
final class CourseControllerTest extends IntegrationTestCase
{
	public function testOwnerCanCreateCourseAndDefaultWorkflowIsCreated(): void
	{
		$owner = Fixture::createUser();
		Fixture::createWorkspace($owner);

		$response = $this->request(
			'POST',
			'/api/courses',
			body: ['name' => 'My Course', 'description' => 'desc'],
			authenticatedAs: $owner,
		);

		self::assertSame(200, $response->getStatusCode());
		$course = $this->jsonBody($response);
		self::assertSame('My Course', $course['name']);
		self::assertNotEmpty($course['prefix']);
		$courseId = self::intField($course['id']);

		// Course shows up in the list
		$listResponse = $this->request('GET', '/api/courses', authenticatedAs: $owner);
		self::assertSame(200, $listResponse->getStatusCode());
		self::assertCount(1, $this->jsonList($listResponse));

		// Default workflow has 3 statuses
		$workflowResponse = $this->request('GET', '/api/courses/' . $courseId . '/workflow', authenticatedAs: $owner);
		self::assertSame(200, $workflowResponse->getStatusCode());
		$workflow = $this->jsonBody($workflowResponse);
		$workflowId = self::intField($workflow['id']);

		$statusRepo = $this->container->get(StatusRepository::class);
		assert($statusRepo instanceof StatusRepository);
		$statuses = iterator_to_array($statusRepo->findByWorkflow($workflowId), false);
		self::assertCount(3, $statuses);
	}

	public function testMemberCannotCreateCourse(): void
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
			'/api/courses',
			body: ['name' => 'X', 'description' => null],
			authenticatedAs: $member,
		);

		self::assertSame(401, $response->getStatusCode());
	}

	public function testAdminCanUpdateAndDeleteCourse(): void
	{
		$owner = Fixture::createUser(email: 'o@example.com');
		$admin = Fixture::createUser(email: 'a@example.com');
		$workspace = Fixture::createWorkspace($owner);
		Fixture::addMember($workspace, $admin, WorkspaceRoleEnum::Admin);
		$admin->currentWorkspaceId = $workspace->id;
		$repo = $this->container->get(UserRepository::class);
		assert($repo instanceof UserRepository);
		$repo->persist($admin);

		$course = Fixture::createCourse($owner, $workspace);

		$update = $this->request(
			'PUT',
			'/api/courses/' . $course->id,
			body: ['name' => 'Renamed', 'description' => null],
			authenticatedAs: $admin,
		);
		self::assertSame(200, $update->getStatusCode());
		self::assertSame('Renamed', $this->jsonBody($update)['name']);

		$delete = $this->request('DELETE', '/api/courses/' . $course->id, authenticatedAs: $admin);
		self::assertSame(200, $delete->getStatusCode());

		$list = $this->request('GET', '/api/courses', authenticatedAs: $admin);
		self::assertCount(0, $this->jsonList($list));
	}

	public function testGetCourseFromAnotherWorkspaceIsNotFound(): void
	{
		$owner = Fixture::createUser(email: 'owner@example.com');
		$workspaceA = Fixture::createWorkspace($owner, 'A');
		$courseInA = Fixture::createCourse($owner, $workspaceA);

		$intruder = Fixture::createUser(email: 'intruder@example.com');
		Fixture::createWorkspace($intruder, 'B');

		$response = $this->request('GET', '/api/courses/' . $courseInA->id, authenticatedAs: $intruder);
		self::assertSame(404, $response->getStatusCode());
	}

	public function testUpdateCourseInAnotherWorkspaceIsNotFound(): void
	{
		$owner = Fixture::createUser(email: 'owner@example.com');
		$workspaceA = Fixture::createWorkspace($owner, 'A');
		$courseInA = Fixture::createCourse($owner, $workspaceA);

		$intruder = Fixture::createUser(email: 'intruder@example.com');
		Fixture::createWorkspace($intruder, 'B');

		$response = $this->request(
			'PUT',
			'/api/courses/' . $courseInA->id,
			body: ['name' => 'Hijacked', 'description' => null],
			authenticatedAs: $intruder,
		);
		self::assertSame(404, $response->getStatusCode());
	}

	public function testDeleteCourseInAnotherWorkspaceIsNotFound(): void
	{
		$owner = Fixture::createUser(email: 'owner@example.com');
		$workspaceA = Fixture::createWorkspace($owner, 'A');
		$courseInA = Fixture::createCourse($owner, $workspaceA);

		$intruder = Fixture::createUser(email: 'intruder@example.com');
		Fixture::createWorkspace($intruder, 'B');

		$response = $this->request('DELETE', '/api/courses/' . $courseInA->id, authenticatedAs: $intruder);
		self::assertSame(404, $response->getStatusCode());
	}

	public function testCreateCourseWithEmptyNameIsRejected(): void
	{
		$owner = Fixture::createUser();
		Fixture::createWorkspace($owner);

		$response = $this->request(
			'POST',
			'/api/courses',
			body: ['name' => '  ', 'description' => null],
			authenticatedAs: $owner,
		);

		self::assertSame(422, $response->getStatusCode());
	}

	public function testCreateCourseWithOverlongNameIsRejected(): void
	{
		$owner = Fixture::createUser();
		Fixture::createWorkspace($owner);

		$response = $this->request(
			'POST',
			'/api/courses',
			body: ['name' => str_repeat('a', 256), 'description' => null],
			authenticatedAs: $owner,
		);

		self::assertSame(422, $response->getStatusCode());
	}
}
