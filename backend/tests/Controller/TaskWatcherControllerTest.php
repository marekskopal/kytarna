<?php

declare(strict_types=1);

namespace Ukolio\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use Ukolio\Controller\TaskWatcherController;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Repository\StatusRepository;
use Ukolio\Model\Repository\WorkflowRepository;
use Ukolio\Tests\Support\Fixture;
use Ukolio\Tests\Support\IntegrationTestCase;

#[CoversClass(TaskWatcherController::class)]
final class TaskWatcherControllerTest extends IntegrationTestCase
{
	public function testWatchListAndUnwatch(): void
	{
		$owner = Fixture::createUser(name: 'Owner');
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$taskId = $this->createTask($owner, $project->id, 'Task');

		$initial = $this->jsonBody($this->request('GET', '/api/tasks/' . $taskId . '/watchers', authenticatedAs: $owner));
		self::assertFalse($initial['watching']);
		self::assertCount(0, $this->watchers($initial));

		$watched = $this->jsonBody($this->request('POST', '/api/tasks/' . $taskId . '/watch', authenticatedAs: $owner));
		self::assertTrue($watched['watching']);
		$watchers = $this->watchers($watched);
		self::assertCount(1, $watchers);
		self::assertSame($owner->id, $watchers[0]['userId']);

		// Idempotent.
		$again = $this->jsonBody($this->request('POST', '/api/tasks/' . $taskId . '/watch', authenticatedAs: $owner));
		self::assertCount(1, $this->watchers($again));

		$unwatched = $this->jsonBody($this->request('DELETE', '/api/tasks/' . $taskId . '/watch', authenticatedAs: $owner));
		self::assertFalse($unwatched['watching']);
		self::assertCount(0, $this->watchers($unwatched));
	}

	public function testForeignTaskIsNotFound(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$taskId = $this->createTask($owner, $project->id, 'Private task');

		$outsider = Fixture::createUser();
		Fixture::createWorkspace($outsider);

		self::assertSame(404, $this->request('GET', '/api/tasks/' . $taskId . '/watchers', authenticatedAs: $outsider)->getStatusCode());
		self::assertSame(404, $this->request('POST', '/api/tasks/' . $taskId . '/watch', authenticatedAs: $outsider)->getStatusCode());
	}

	/**
	 * @param array<string, mixed> $body
	 * @return list<array<array-key, mixed>>
	 */
	private function watchers(array $body): array
	{
		$watchers = $body['watchers'];
		self::assertIsArray($watchers);
		$result = [];
		foreach ($watchers as $watcher) {
			self::assertIsArray($watcher);
			$result[] = $watcher;
		}
		return $result;
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
