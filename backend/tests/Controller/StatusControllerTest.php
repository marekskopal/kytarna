<?php

declare(strict_types=1);

namespace Ukolio\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use Ukolio\Controller\StatusController;
use Ukolio\Model\Repository\StatusRepository;
use Ukolio\Model\Repository\WorkflowRepository;
use Ukolio\Tests\Support\Fixture;
use Ukolio\Tests\Support\IntegrationTestCase;

#[CoversClass(StatusController::class)]
final class StatusControllerTest extends IntegrationTestCase
{
	public function testCreateUpdateMoveAndDeleteStatus(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$workflowId = $this->workflowId($project->id);

		// Create a new status
		$create = $this->request(
			'POST',
			'/api/workflows/' . $workflowId . '/statuses',
			body: ['name' => 'Review', 'color' => '#abcdef', 'type' => 'Normal', 'position' => 2],
			authenticatedAs: $owner,
		);
		self::assertSame(200, $create->getStatusCode());
		$statusId = self::intField($this->jsonBody($create)['id']);

		// Update
		$update = $this->request(
			'PUT',
			'/api/statuses/' . $statusId,
			body: ['name' => 'In Review', 'color' => '#fedcba', 'type' => 'Normal'],
			authenticatedAs: $owner,
		);
		self::assertSame(200, $update->getStatusCode());
		self::assertSame('In Review', $this->jsonBody($update)['name']);

		// Move
		$move = $this->request(
			'PUT',
			'/api/statuses/' . $statusId . '/move',
			body: ['position' => 0],
			authenticatedAs: $owner,
		);
		self::assertSame(200, $move->getStatusCode());
		self::assertSame(0, $this->jsonBody($move)['position']);

		// Delete
		$delete = $this->request('DELETE', '/api/statuses/' . $statusId, authenticatedAs: $owner);
		self::assertSame(200, $delete->getStatusCode());

		// Status is gone — update returns 404
		$missing = $this->request(
			'PUT',
			'/api/statuses/' . $statusId,
			body: ['name' => 'Gone', 'color' => '#000000', 'type' => 'Normal'],
			authenticatedAs: $owner,
		);
		self::assertSame(404, $missing->getStatusCode());
	}

	public function testCannotDeleteLastStatus(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$workflowId = $this->workflowId($project->id);

		// Default workflow has 3 statuses. Delete two; the third must remain.
		$statuses = $this->statusIdsFor($workflowId);
		$this->request('DELETE', '/api/statuses/' . $statuses[0], authenticatedAs: $owner);
		$this->request('DELETE', '/api/statuses/' . $statuses[1], authenticatedAs: $owner);

		$delete = $this->request('DELETE', '/api/statuses/' . $statuses[2], authenticatedAs: $owner);
		self::assertSame(422, $delete->getStatusCode());
	}

	private function workflowId(int $projectId): int
	{
		$workflowRepo = $this->container->get(WorkflowRepository::class);
		assert($workflowRepo instanceof WorkflowRepository);
		$workflow = $workflowRepo->findByProject($projectId);
		assert($workflow !== null);
		return $workflow->id;
	}

	/** @return list<int> */
	private function statusIdsFor(int $workflowId): array
	{
		$statusRepo = $this->container->get(StatusRepository::class);
		assert($statusRepo instanceof StatusRepository);
		$ids = [];
		foreach ($statusRepo->findByWorkflow($workflowId) as $status) {
			$ids[] = $status->id;
		}
		return $ids;
	}
}
