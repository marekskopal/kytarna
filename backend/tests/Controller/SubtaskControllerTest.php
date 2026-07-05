<?php

declare(strict_types=1);

namespace Ukolio\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use Ukolio\Controller\SubtaskController;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Repository\StatusRepository;
use Ukolio\Model\Repository\WorkflowRepository;
use Ukolio\Tests\Support\Fixture;
use Ukolio\Tests\Support\IntegrationTestCase;

#[CoversClass(SubtaskController::class)]
final class SubtaskControllerTest extends IntegrationTestCase
{
	public function testCreateAndListSubtasks(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$parentId = $this->createTask($owner, $project->id, 'Parent task');

		$create = $this->request(
			'POST',
			'/api/tasks/' . $parentId . '/subtasks',
			body: ['name' => 'Child one'],
			authenticatedAs: $owner,
		);
		self::assertSame(201, $create->getStatusCode());
		$subtask = $this->jsonBody($create);
		self::assertSame('Child one', $subtask['name']);
		self::assertSame('Start', $subtask['statusType']);
		self::assertNotNull($subtask['startStatusId']);
		self::assertNotNull($subtask['finishStatusId']);
		self::assertIsInt($subtask['relationId']);

		$list = $this->request('GET', '/api/tasks/' . $parentId . '/subtasks', authenticatedAs: $owner);
		self::assertSame(200, $list->getStatusCode());
		$items = $this->jsonList($list);
		self::assertCount(1, $items);

		$empty = $this->request(
			'POST',
			'/api/tasks/' . $parentId . '/subtasks',
			body: ['name' => '   '],
			authenticatedAs: $owner,
		);
		self::assertSame(422, $empty->getStatusCode());
	}

	public function testSubtaskCountsOnBoardAndWorkspaceList(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$parentId = $this->createTask($owner, $project->id, 'Parent task');

		$first = $this->jsonBody($this->request(
			'POST',
			'/api/tasks/' . $parentId . '/subtasks',
			body: ['name' => 'Child A'],
			authenticatedAs: $owner,
		));
		$this->request(
			'POST',
			'/api/tasks/' . $parentId . '/subtasks',
			body: ['name' => 'Child B'],
			authenticatedAs: $owner,
		);

		// Complete the first child by moving it to its Finish status.
		$move = $this->request(
			'PUT',
			'/api/tasks/' . self::intField($first['taskId']) . '/move',
			body: ['statusId' => self::intField($first['finishStatusId']), 'position' => 0],
			authenticatedAs: $owner,
		);
		self::assertSame(200, $move->getStatusCode());

		$board = $this->jsonBody($this->request('GET', '/api/projects/' . $project->id . '/board', authenticatedAs: $owner));
		$boardTasks = $board['tasks'];
		self::assertIsArray($boardTasks);
		$parentOnBoard = null;
		foreach ($boardTasks as $task) {
			self::assertIsArray($task);
			if (self::intField($task['id']) === $parentId) {
				$parentOnBoard = $task;
			}
		}
		self::assertNotNull($parentOnBoard);
		self::assertSame(2, $parentOnBoard['subtasksTotal']);
		self::assertSame(1, $parentOnBoard['subtasksDone']);

		$list = $this->jsonBody($this->request('GET', '/api/tasks?orderBy=name&orderDirection=ASC', authenticatedAs: $owner));
		$listTasks = $list['tasks'];
		self::assertIsArray($listTasks);
		$parentInList = null;
		foreach ($listTasks as $task) {
			self::assertIsArray($task);
			if (self::intField($task['id']) === $parentId) {
				$parentInList = $task;
			}
		}
		self::assertNotNull($parentInList);
		self::assertSame(2, $parentInList['subtasksTotal']);
		self::assertSame(1, $parentInList['subtasksDone']);
	}

	public function testSubtaskFiltersOnWorkspaceList(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$parentId = $this->createTask($owner, $project->id, 'Parent task');
		$this->createTask($owner, $project->id, 'Standalone task');
		$this->request(
			'POST',
			'/api/tasks/' . $parentId . '/subtasks',
			body: ['name' => 'Child task'],
			authenticatedAs: $owner,
		);

		$all = $this->jsonBody($this->request('GET', '/api/tasks', authenticatedAs: $owner));
		self::assertSame(3, $all['count']);

		$hidden = $this->jsonBody($this->request('GET', '/api/tasks?subtaskFilter=hideSubtasks', authenticatedAs: $owner));
		self::assertSame(2, $hidden['count']);
		$hiddenTasks = $hidden['tasks'];
		self::assertIsArray($hiddenTasks);
		foreach ($hiddenTasks as $task) {
			self::assertIsArray($task);
			self::assertNotSame('Child task', $task['name']);
		}

		$parents = $this->jsonBody($this->request('GET', '/api/tasks?subtaskFilter=onlyParents', authenticatedAs: $owner));
		self::assertSame(1, $parents['count']);
		$parentTasks = $parents['tasks'];
		self::assertIsArray($parentTasks);
		self::assertIsArray($parentTasks[0]);
		self::assertSame('Parent task', $parentTasks[0]['name']);

		$invalid = $this->request('GET', '/api/tasks?subtaskFilter=bogus', authenticatedAs: $owner);
		self::assertSame(400, $invalid->getStatusCode());
	}

	public function testSubtasksOfForeignTaskAreNotFound(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$parentId = $this->createTask($owner, $project->id, 'Private parent');

		$outsider = Fixture::createUser();
		Fixture::createWorkspace($outsider);

		$list = $this->request('GET', '/api/tasks/' . $parentId . '/subtasks', authenticatedAs: $outsider);
		self::assertSame(404, $list->getStatusCode());

		$create = $this->request(
			'POST',
			'/api/tasks/' . $parentId . '/subtasks',
			body: ['name' => 'Sneaky'],
			authenticatedAs: $outsider,
		);
		self::assertSame(404, $create->getStatusCode());
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
