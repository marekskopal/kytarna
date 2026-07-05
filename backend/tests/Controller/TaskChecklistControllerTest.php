<?php

declare(strict_types=1);

namespace Ukolio\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use Ukolio\Controller\TaskChecklistController;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Repository\StatusRepository;
use Ukolio\Model\Repository\WorkflowRepository;
use Ukolio\Tests\Support\Fixture;
use Ukolio\Tests\Support\IntegrationTestCase;

#[CoversClass(TaskChecklistController::class)]
final class TaskChecklistControllerTest extends IntegrationTestCase
{
	public function testCreateListToggleAndDelete(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$taskId = $this->createTask($owner, $project->id, 'Task with checklist');

		$create = $this->request(
			'POST',
			'/api/tasks/' . $taskId . '/checklist',
			body: ['text' => 'Write the spec'],
			authenticatedAs: $owner,
		);
		self::assertSame(201, $create->getStatusCode());
		$item = $this->jsonBody($create);
		self::assertSame('Write the spec', $item['text']);
		self::assertFalse($item['checked']);
		self::assertSame(0, $item['position']);

		$empty = $this->request(
			'POST',
			'/api/tasks/' . $taskId . '/checklist',
			body: ['text' => '   '],
			authenticatedAs: $owner,
		);
		self::assertSame(422, $empty->getStatusCode());

		$itemId = self::intField($item['id']);
		$toggle = $this->request(
			'PUT',
			'/api/checklist-items/' . $itemId,
			body: ['checked' => true],
			authenticatedAs: $owner,
		);
		self::assertSame(200, $toggle->getStatusCode());
		$toggled = $this->jsonBody($toggle);
		self::assertTrue($toggled['checked']);
		self::assertSame($owner->id, $toggled['checkedById']);

		$list = $this->request('GET', '/api/tasks/' . $taskId . '/checklist', authenticatedAs: $owner);
		self::assertSame(200, $list->getStatusCode());
		self::assertCount(1, $this->jsonList($list));

		$delete = $this->request('DELETE', '/api/checklist-items/' . $itemId, authenticatedAs: $owner);
		self::assertSame(200, $delete->getStatusCode());
		self::assertCount(0, $this->jsonList($this->request('GET', '/api/tasks/' . $taskId . '/checklist', authenticatedAs: $owner)));
	}

	public function testUpdateTextDueDateAndAssignee(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$taskId = $this->createTask($owner, $project->id, 'Task');

		$item = $this->jsonBody($this->request(
			'POST',
			'/api/tasks/' . $taskId . '/checklist',
			body: ['text' => 'Initial'],
			authenticatedAs: $owner,
		));
		$itemId = self::intField($item['id']);

		$updated = $this->jsonBody($this->request(
			'PUT',
			'/api/checklist-items/' . $itemId,
			body: ['text' => 'Renamed', 'dueDate' => '2026-07-01', 'assigneeId' => $owner->id],
			authenticatedAs: $owner,
		));
		self::assertSame('Renamed', $updated['text']);
		self::assertSame('2026-07-01', $updated['dueDate']);
		self::assertSame($owner->id, $updated['assigneeId']);

		// Clearing due date (explicit null) and unassigning (explicit null).
		$cleared = $this->jsonBody($this->request(
			'PUT',
			'/api/checklist-items/' . $itemId,
			body: ['dueDate' => null, 'assigneeId' => null],
			authenticatedAs: $owner,
		));
		self::assertNull($cleared['dueDate']);
		self::assertNull($cleared['assigneeId']);
		self::assertSame('Renamed', $cleared['text']);
	}

	public function testReorderItems(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$taskId = $this->createTask($owner, $project->id, 'Task');

		$ids = [];
		foreach (['A', 'B', 'C'] as $text) {
			$created = $this->jsonBody($this->request(
				'POST',
				'/api/tasks/' . $taskId . '/checklist',
				body: ['text' => $text],
				authenticatedAs: $owner,
			));
			$ids[$text] = self::intField($created['id']);
		}

		// Move C to the front.
		$this->request('PUT', '/api/checklist-items/' . $ids['C'] . '/move', body: ['position' => 0], authenticatedAs: $owner);

		$items = $this->jsonList($this->request('GET', '/api/tasks/' . $taskId . '/checklist', authenticatedAs: $owner));
		self::assertSame('C', $items[0]['text']);
		self::assertSame('A', $items[1]['text']);
		self::assertSame('B', $items[2]['text']);
	}

	public function testChecklistCountsOnBoardAndList(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$taskId = $this->createTask($owner, $project->id, 'Counted task');

		$first = $this->jsonBody($this->request(
			'POST',
			'/api/tasks/' . $taskId . '/checklist',
			body: ['text' => 'one'],
			authenticatedAs: $owner,
		));
		$this->request('POST', '/api/tasks/' . $taskId . '/checklist', body: ['text' => 'two'], authenticatedAs: $owner);
		$this->request(
			'PUT',
			'/api/checklist-items/' . self::intField($first['id']),
			body: ['checked' => true],
			authenticatedAs: $owner,
		);

		$board = $this->jsonBody($this->request('GET', '/api/projects/' . $project->id . '/board', authenticatedAs: $owner));
		$boardTasks = $board['tasks'];
		self::assertIsArray($boardTasks);
		$found = null;
		foreach ($boardTasks as $task) {
			self::assertIsArray($task);
			if (self::intField($task['id']) === $taskId) {
				$found = $task;
			}
		}
		self::assertNotNull($found);
		self::assertSame(2, $found['checklistTotal']);
		self::assertSame(1, $found['checklistDone']);

		$list = $this->jsonBody($this->request('GET', '/api/tasks?orderBy=name&orderDirection=ASC', authenticatedAs: $owner));
		$listTasks = $list['tasks'];
		self::assertIsArray($listTasks);
		$inList = null;
		foreach ($listTasks as $task) {
			self::assertIsArray($task);
			if (self::intField($task['id']) === $taskId) {
				$inList = $task;
			}
		}
		self::assertNotNull($inList);
		self::assertSame(2, $inList['checklistTotal']);
		self::assertSame(1, $inList['checklistDone']);
	}

	public function testForeignTaskChecklistIsNotFound(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$taskId = $this->createTask($owner, $project->id, 'Private task');

		$outsider = Fixture::createUser();
		Fixture::createWorkspace($outsider);

		self::assertSame(404, $this->request('GET', '/api/tasks/' . $taskId . '/checklist', authenticatedAs: $outsider)->getStatusCode());
		self::assertSame(404, $this->request(
			'POST',
			'/api/tasks/' . $taskId . '/checklist',
			body: ['text' => 'Sneaky'],
			authenticatedAs: $outsider,
		)->getStatusCode());
	}

	private function createTask(User $author, int $projectId, string $name): int
	{
		$response = $this->request(
			'POST',
			'/api/projects/' . $projectId . '/tasks',
			body: ['statusId' => $this->firstStatusId($projectId), 'name' => $name, 'description' => null, 'priority' => 'Medium'],
			authenticatedAs: $author,
		);
		return self::intField($this->jsonBody($response)['id']);
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

		self::fail('Project has no statuses.');
	}
}
