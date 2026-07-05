<?php

declare(strict_types=1);

namespace Kytario\Tests\Service\Notification;

use DateTimeImmutable;
use Kytario\Model\Entity\Enum\ActorTypeEnum;
use Kytario\Model\Entity\Enum\EventTypeEnum;
use Kytario\Model\Entity\Enum\NotificationTypeEnum;
use Kytario\Model\Entity\Enum\WorkspaceRoleEnum;
use Kytario\Model\Entity\Event;
use Kytario\Model\Entity\Notification;
use Kytario\Model\Entity\Project;
use Kytario\Model\Entity\Task;
use Kytario\Model\Entity\User;
use Kytario\Model\Entity\Workspace;
use Kytario\Model\Repository\StatusRepository;
use Kytario\Model\Repository\TaskRepository;
use Kytario\Model\Repository\WorkflowRepository;
use Kytario\Service\Notification\NotificationDispatcher;
use Kytario\Service\Notification\NotificationDispatcherInterface;
use Kytario\Service\Provider\NotificationProviderInterface;
use Kytario\Service\Provider\TaskWatcherProviderInterface;
use Kytario\Tests\Support\Fixture;
use Kytario\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use const JSON_THROW_ON_ERROR;

#[CoversClass(NotificationDispatcher::class)]
final class NotificationDispatcherTest extends IntegrationTestCase
{
	public function testAssignmentNotifiesAssigneeAndAutoWatches(): void
	{
		$owner = Fixture::createUser(name: 'Owner');
		$workspace = Fixture::createWorkspace($owner);
		$bob = $this->createMember($workspace, 'Bob');
		$project = Fixture::createProject($owner, $workspace);

		$task = $this->createTask($owner, $project->id, 'Assigned task', $bob->id);

		self::assertSame([NotificationTypeEnum::TaskAssigned->value], $this->typesFor($bob));
		self::assertTrue($this->watcherProvider()->isWatching($task, $bob));
		// The actor (owner) is never notified about their own action.
		self::assertCount(0, $this->notificationsFor($owner));
	}

	public function testHumanMoveNotifiesWatchersButAgentMoveIsSuppressed(): void
	{
		$owner = Fixture::createUser(name: 'Owner');
		$workspace = Fixture::createWorkspace($owner);
		$bob = $this->createMember($workspace, 'Bob');
		$project = Fixture::createProject($owner, $workspace);

		$task = $this->createTask($owner, $project->id, 'Movable task', $bob->id);
		$taskId = $task->id;

		// An agent-driven move must not ping watchers (agents churn statuses).
		$this->dispatcher()->onEvent($this->moveEvent($owner, $workspace, $project, $task, ActorTypeEnum::Agent));
		self::assertCount(0, $this->ofType($bob, NotificationTypeEnum::TaskMoved));

		// A human move pings the watcher (Bob), but not the actor (owner).
		$secondStatusId = $this->statusIdAtIndex($project->id, 1);
		$this->request(
			'PUT',
			'/api/tasks/' . $taskId . '/move',
			body: ['statusId' => $secondStatusId, 'position' => 0],
			authenticatedAs: $owner,
		);

		self::assertCount(1, $this->ofType($bob, NotificationTypeEnum::TaskMoved));
		self::assertCount(0, $this->ofType($owner, NotificationTypeEnum::TaskMoved));
	}

	private function moveEvent(User $author, Workspace $workspace, Project $project, Task $task, ActorTypeEnum $actorType): Event
	{
		$event = new Event(
			author: $author,
			type: EventTypeEnum::TaskMoved,
			metadata: json_encode(['toStatusName' => 'In Progress', 'taskName' => $task->name], JSON_THROW_ON_ERROR),
			project: $project,
			workspaceId: $workspace->id,
			taskId: $task->id,
			actorType: $actorType,
		);
		$event->createdAt = new DateTimeImmutable();
		$event->updatedAt = new DateTimeImmutable();
		return $event;
	}

	/** @return list<Notification> */
	private function ofType(User $user, NotificationTypeEnum $type): array
	{
		return array_values(array_filter(
			$this->notificationsFor($user),
			static fn (Notification $n): bool => $n->type === $type,
		));
	}

	/** @return list<string> */
	private function typesFor(User $user): array
	{
		return array_map(
			static fn (Notification $n): string => $n->type->value,
			$this->notificationsFor($user),
		);
	}

	/** @return list<Notification> */
	private function notificationsFor(User $user): array
	{
		$provider = $this->container->get(NotificationProviderInterface::class);
		assert($provider instanceof NotificationProviderInterface);
		return $provider->listForUser($user, 100, 0, false);
	}

	private function dispatcher(): NotificationDispatcherInterface
	{
		$dispatcher = $this->container->get(NotificationDispatcherInterface::class);
		assert($dispatcher instanceof NotificationDispatcherInterface);
		return $dispatcher;
	}

	private function watcherProvider(): TaskWatcherProviderInterface
	{
		$provider = $this->container->get(TaskWatcherProviderInterface::class);
		assert($provider instanceof TaskWatcherProviderInterface);
		return $provider;
	}

	private function createMember(Workspace $workspace, string $name): User
	{
		$user = Fixture::createUser(name: $name);
		Fixture::addMember($workspace, $user, WorkspaceRoleEnum::Member);
		return $user;
	}

	private function createTask(User $author, int $projectId, string $name, ?int $assigneeId = null): Task
	{
		$body = ['statusId' => $this->firstStatusId($projectId), 'name' => $name, 'description' => null];
		if ($assigneeId !== null) {
			$body['assigneeId'] = $assigneeId;
		}

		$response = $this->request('POST', '/api/projects/' . $projectId . '/tasks', body: $body, authenticatedAs: $author);
		self::assertSame(200, $response->getStatusCode());

		return $this->task(self::intField($this->jsonBody($response)['id']));
	}

	private function task(int $taskId): Task
	{
		$taskRepository = $this->container->get(TaskRepository::class);
		assert($taskRepository instanceof TaskRepository);
		$task = $taskRepository->findById($taskId);
		assert($task instanceof Task);
		return $task;
	}

	private function firstStatusId(int $projectId): int
	{
		return $this->statusIdAtIndex($projectId, 0);
	}

	private function statusIdAtIndex(int $projectId, int $index): int
	{
		$workflowRepo = $this->container->get(WorkflowRepository::class);
		assert($workflowRepo instanceof WorkflowRepository);
		$workflow = $workflowRepo->findByProject($projectId);
		assert($workflow !== null);

		$statusRepo = $this->container->get(StatusRepository::class);
		assert($statusRepo instanceof StatusRepository);
		$ids = [];
		foreach ($statusRepo->findByWorkflow($workflow->id) as $status) {
			$ids[] = $status->id;
		}
		self::assertArrayHasKey($index, $ids);
		return $ids[$index];
	}
}
