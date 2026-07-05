<?php

declare(strict_types=1);

namespace Kytario\Tests\Service\Provider;

use PHPUnit\Framework\Attributes\CoversClass;
use Kytario\Model\Entity\Enum\WorkspaceRoleEnum;
use Kytario\Model\Entity\Task;
use Kytario\Model\Entity\User;
use Kytario\Model\Repository\StatusRepository;
use Kytario\Model\Repository\TaskRepository;
use Kytario\Model\Repository\WorkflowRepository;
use Kytario\Service\Provider\TaskWatcherProvider;
use Kytario\Service\Provider\TaskWatcherProviderInterface;
use Kytario\Tests\Support\Fixture;
use Kytario\Tests\Support\IntegrationTestCase;

#[CoversClass(TaskWatcherProvider::class)]
final class TaskWatcherProviderTest extends IntegrationTestCase
{
	public function testWatchIsIdempotentAndUnwatchRemoves(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$project = Fixture::createProject($owner, $workspace);
		$task = $this->createTask($owner, $project->id, 'Watched task');

		$provider = $this->watcherProvider();

		self::assertFalse($provider->isWatching($task, $owner));

		$provider->watch($task, $owner);
		$provider->watch($task, $owner);

		self::assertTrue($provider->isWatching($task, $owner));
		self::assertCount(1, $provider->listWatchers($task));
		self::assertSame([$owner->id], $provider->listWatcherUserIds($task));

		$provider->unwatch($task, $owner);

		self::assertFalse($provider->isWatching($task, $owner));
		self::assertCount(0, $provider->listWatchers($task));
	}

	public function testDeleteAllForTaskClearsEveryWatcher(): void
	{
		$owner = Fixture::createUser();
		$member = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		Fixture::addMember($workspace, $member, WorkspaceRoleEnum::Member);
		$project = Fixture::createProject($owner, $workspace);
		$task = $this->createTask($owner, $project->id, 'Shared task');

		$provider = $this->watcherProvider();
		$provider->watch($task, $owner);
		$provider->watch($task, $member);
		self::assertCount(2, $provider->listWatchers($task));

		$provider->deleteAllForTask($task);

		self::assertCount(0, $provider->listWatchers($task));
	}

	private function watcherProvider(): TaskWatcherProviderInterface
	{
		$provider = $this->container->get(TaskWatcherProviderInterface::class);
		assert($provider instanceof TaskWatcherProviderInterface);
		return $provider;
	}

	private function createTask(User $author, int $projectId, string $name): Task
	{
		$response = $this->request(
			'POST',
			'/api/projects/' . $projectId . '/tasks',
			body: ['statusId' => $this->firstStatusId($projectId), 'name' => $name, 'description' => null, 'priority' => 'Medium'],
			authenticatedAs: $author,
		);
		$taskId = self::intField($this->jsonBody($response)['id']);

		$taskRepository = $this->container->get(TaskRepository::class);
		assert($taskRepository instanceof TaskRepository);
		$task = $taskRepository->findById($taskId);
		assert($task instanceof Task);
		return $task;
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
