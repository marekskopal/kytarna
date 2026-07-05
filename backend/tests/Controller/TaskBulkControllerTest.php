<?php

declare(strict_types=1);

namespace Ukolio\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use Ukolio\Controller\TaskBulkController;
use Ukolio\Model\Entity\Enum\EventTypeEnum;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Model\Repository\EventRepository;
use Ukolio\Model\Repository\StatusRepository;
use Ukolio\Model\Repository\WorkflowRepository;
use Ukolio\Tests\Support\Fixture;
use Ukolio\Tests\Support\IntegrationTestCase;

#[CoversClass(TaskBulkController::class)]
final class TaskBulkControllerTest extends IntegrationTestCase
{
	public function testBulkMoveSucceedsAndAppendsToTarget(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		[$todoId, $inProgressId] = $this->statusIds($project->id);

		$ids = $this->createTasks($owner, $project->id, $todoId, ['A', 'B', 'C']);

		$response = $this->request(
			'POST',
			'/api/tasks/bulk',
			body: ['ids' => $ids, 'op' => 'move', 'payload' => ['statusId' => $inProgressId]],
			authenticatedAs: $owner,
		);

		self::assertSame(200, $response->getStatusCode());
		$body = $this->jsonBody($response);
		self::assertSame($ids, $body['succeeded']);
		self::assertSame([], $body['skipped']);

		// All three tasks are now in the target column, in input order.
		foreach ($ids as $id) {
			$task = $this->jsonBody($this->request('GET', '/api/tasks/' . $id, authenticatedAs: $owner));
			self::assertSame($inProgressId, $task['statusId']);
		}

		$this->assertExactlyOneBulkEvent($workspace, 'move', $ids);
	}

	public function testBulkPartialSkipForUnknownAndOutOfWorkspaceIds(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$todoId = $this->firstStatusId($project->id);
		$ids = $this->createTasks($owner, $project->id, $todoId, ['Mine']);
		$mineId = $ids[0];

		// Task in a foreign workspace owned by someone else.
		$other = Fixture::createUser(email: 'other@example.com');
		$otherWorkspace = Fixture::createWorkspace($other, 'Other');
		$otherProject = Fixture::createProject($other, $otherWorkspace);
		$otherTodoId = $this->firstStatusId($otherProject->id);
		$foreignIds = $this->createTasks($other, $otherProject->id, $otherTodoId, ['Theirs']);
		$foreignId = $foreignIds[0];

		$missingId = 999999;
		$mediumPriority = $this->resolvePriorityId($workspace, 'Medium');

		$response = $this->request(
			'POST',
			'/api/tasks/bulk',
			body: [
				'ids' => [$mineId, $foreignId, $missingId],
				'op' => 'priority',
				'payload' => ['priorityId' => $mediumPriority],
			],
			authenticatedAs: $owner,
		);

		self::assertSame(200, $response->getStatusCode());
		$body = $this->jsonBody($response);
		self::assertSame([$mineId], $body['succeeded']);
		$skipped = $body['skipped'];
		self::assertIsArray($skipped);
		$reasons = array_column($skipped, 'reason', 'id');
		self::assertSame('out_of_workspace', $reasons[$foreignId]);
		self::assertSame('not_found', $reasons[$missingId]);
	}

	public function testInvalidOpReturns422(): void
	{
		$owner = Fixture::createUser();
		Fixture::createWorkspace($owner);

		$response = $this->request(
			'POST',
			'/api/tasks/bulk',
			body: ['ids' => [1], 'op' => 'rename'],
			authenticatedAs: $owner,
		);

		self::assertSame(422, $response->getStatusCode());
	}

	public function testEmptyIdsReturns422(): void
	{
		$owner = Fixture::createUser();
		Fixture::createWorkspace($owner);

		$response = $this->request(
			'POST',
			'/api/tasks/bulk',
			body: ['ids' => [], 'op' => 'delete'],
			authenticatedAs: $owner,
		);

		self::assertSame(422, $response->getStatusCode());
	}

	public function testTooManyIdsReturns422(): void
	{
		$owner = Fixture::createUser();
		Fixture::createWorkspace($owner);

		$ids = range(1, 201);

		$response = $this->request(
			'POST',
			'/api/tasks/bulk',
			body: ['ids' => $ids, 'op' => 'delete'],
			authenticatedAs: $owner,
		);

		self::assertSame(422, $response->getStatusCode());
	}

	public function testNoWorkspaceReturns422(): void
	{
		// no workspace, no current selection
		$loner = Fixture::createUser();

		$response = $this->request(
			'POST',
			'/api/tasks/bulk',
			body: ['ids' => [1], 'op' => 'delete'],
			authenticatedAs: $loner,
		);

		self::assertSame(422, $response->getStatusCode());
	}

	public function testBulkDeleteRemovesTasksAndWritesSingleEvent(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$todoId = $this->firstStatusId($project->id);
		$ids = $this->createTasks($owner, $project->id, $todoId, ['X', 'Y']);

		$response = $this->request(
			'POST',
			'/api/tasks/bulk',
			body: ['ids' => $ids, 'op' => 'delete'],
			authenticatedAs: $owner,
		);

		self::assertSame(200, $response->getStatusCode());
		self::assertSame($ids, $this->jsonBody($response)['succeeded']);

		foreach ($ids as $id) {
			$get = $this->request('GET', '/api/tasks/' . $id, authenticatedAs: $owner);
			self::assertSame(404, $get->getStatusCode());
		}

		$this->assertExactlyOneBulkEvent($workspace, 'delete', $ids);
	}

	public function testBulkTagIsAdditive(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$todoId = $this->firstStatusId($project->id);
		$ids = $this->createTasks($owner, $project->id, $todoId, ['T1', 'T2']);

		$tagId = $this->createTag($workspace, 'urgent');

		$response = $this->request(
			'POST',
			'/api/tasks/bulk',
			body: ['ids' => $ids, 'op' => 'tag', 'payload' => ['tagIds' => [$tagId]]],
			authenticatedAs: $owner,
		);

		self::assertSame(200, $response->getStatusCode());

		foreach ($ids as $id) {
			$task = $this->jsonBody($this->request('GET', '/api/tasks/' . $id, authenticatedAs: $owner));
			self::assertSame([$tagId], $task['tagIds']);
		}
	}

	/**
	 * @param list<string> $names
	 * @return list<int>
	 */
	private function createTasks(User $owner, int $projectId, int $statusId, array $names): array
	{
		$ids = [];
		foreach ($names as $name) {
			$create = $this->request(
				'POST',
				'/api/projects/' . $projectId . '/tasks',
				body: ['statusId' => $statusId, 'name' => $name, 'description' => null, 'priority' => 'Medium'],
				authenticatedAs: $owner,
			);
			self::assertSame(200, $create->getStatusCode(), 'create task ' . $name);
			$ids[] = self::intField($this->jsonBody($create)['id']);
		}
		return $ids;
	}

	private function createTag(Workspace $workspace, string $name): int
	{
		$response = $this->request(
			'POST',
			'/api/workspaces/' . $workspace->id . '/tags',
			body: ['name' => $name, 'color' => '#ff0000'],
			authenticatedAs: $workspace->owner,
		);
		self::assertSame(200, $response->getStatusCode(), 'create tag ' . $name);
		return self::intField($this->jsonBody($response)['id']);
	}

	private function resolvePriorityId(Workspace $workspace, string $name): int
	{
		$response = $this->request('GET', '/api/workspaces/' . $workspace->id . '/priorities', authenticatedAs: $workspace->owner);
		foreach ($this->jsonList($response) as $priority) {
			if (($priority['name'] ?? null) === $name) {
				return self::intField($priority['id']);
			}
		}
		self::fail(sprintf('Priority "%s" not found in workspace %d.', $name, $workspace->id));
	}

	/** @param list<int> $expectedIds */
	private function assertExactlyOneBulkEvent(Workspace $workspace, string $expectedOp, array $expectedIds): void
	{
		$eventRepo = $this->container->get(EventRepository::class);
		assert($eventRepo instanceof EventRepository);
		$matching = [];
		foreach ($eventRepo->findByWorkspace($workspace->id, null, 50, 0) as $event) {
			if ($event->type === EventTypeEnum::TasksBulkUpdated) {
				$matching[] = $event;
			}
		}
		self::assertCount(1, $matching, 'Expected exactly one TasksBulkUpdated event');
		$event = $matching[0];
		self::assertSame($workspace->id, $event->workspaceId);
		self::assertNull($event->project);
		self::assertNull($event->taskId);
		$meta = json_decode($event->metadata, true);
		self::assertIsArray($meta);
		self::assertSame($expectedOp, $meta['op']);
		self::assertSame($expectedIds, $meta['succeededIds']);
	}

	/** @return array{0:int,1:int} */
	private function statusIds(int $projectId): array
	{
		$workflowRepo = $this->container->get(WorkflowRepository::class);
		assert($workflowRepo instanceof WorkflowRepository);
		$workflow = $workflowRepo->findByProject($projectId);
		assert($workflow !== null);
		$statusRepo = $this->container->get(StatusRepository::class);
		assert($statusRepo instanceof StatusRepository);
		$statuses = [];
		foreach ($statusRepo->findByWorkflow($workflow->id) as $status) {
			$statuses[] = $status->id;
		}
		return [$statuses[0], $statuses[1]];
	}

	private function firstStatusId(int $projectId): int
	{
		return $this->statusIds($projectId)[0];
	}
}
