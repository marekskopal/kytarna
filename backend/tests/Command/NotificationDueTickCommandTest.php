<?php

declare(strict_types=1);

namespace Ukolio\Tests\Command;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Console\Tester\CommandTester;
use Ukolio\Command\NotificationDueTickCommand;
use Ukolio\Model\Entity\Enum\NotificationTypeEnum;
use Ukolio\Model\Entity\Enum\WorkspaceRoleEnum;
use Ukolio\Model\Entity\Notification;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Model\Repository\StatusRepository;
use Ukolio\Model\Repository\WorkflowRepository;
use Ukolio\Service\Provider\NotificationProviderInterface;
use Ukolio\Tests\Support\Fixture;
use Ukolio\Tests\Support\IntegrationTestCase;

#[CoversClass(NotificationDueTickCommand::class)]
final class NotificationDueTickCommandTest extends IntegrationTestCase
{
	public function testRemindsAssigneeAndWatchersOncePerDay(): void
	{
		$owner = Fixture::createUser(name: 'Owner');
		$workspace = Fixture::createWorkspace($owner);
		$bob = $this->createMember($workspace, 'Bob');
		$project = Fixture::createProject($owner, $workspace);

		$today = (new DateTimeImmutable('today'))->format('Y-m-d');
		$taskId = $this->createTask($owner, $project->id, 'Due today', $bob->id, $today);

		// Owner watches the task explicitly (Bob already watches as assignee).
		$this->request('POST', '/api/tasks/' . $taskId . '/watch', authenticatedAs: $owner);

		$this->runTick();

		self::assertCount(1, $this->ofType($bob, NotificationTypeEnum::DueToday));
		self::assertCount(1, $this->ofType($owner, NotificationTypeEnum::DueToday));

		// A second run the same day must not re-send (per-day de-duplication).
		$this->runTick();
		self::assertCount(1, $this->ofType($bob, NotificationTypeEnum::DueToday));
		self::assertCount(1, $this->ofType($owner, NotificationTypeEnum::DueToday));
	}

	public function testTaskDueLaterIsNotReminded(): void
	{
		$owner = Fixture::createUser(name: 'Owner');
		$workspace = Fixture::createWorkspace($owner);
		$bob = $this->createMember($workspace, 'Bob');
		$project = Fixture::createProject($owner, $workspace);

		$inThreeDays = (new DateTimeImmutable('today'))->modify('+3 days')->format('Y-m-d');
		$this->createTask($owner, $project->id, 'Due later', $bob->id, $inThreeDays);

		$this->runTick();

		self::assertCount(0, $this->ofType($bob, NotificationTypeEnum::DueToday));
		self::assertCount(0, $this->ofType($bob, NotificationTypeEnum::DueSoon));
	}

	private function runTick(): void
	{
		$tester = new CommandTester(new NotificationDueTickCommand());
		$tester->execute([]);
		self::assertSame(0, $tester->getStatusCode());
	}

	/** @return list<Notification> */
	private function ofType(User $user, NotificationTypeEnum $type): array
	{
		$provider = $this->container->get(NotificationProviderInterface::class);
		assert($provider instanceof NotificationProviderInterface);

		return array_values(array_filter(
			$provider->listForUser($user, 100, 0, false),
			static fn (Notification $n): bool => $n->type === $type,
		));
	}

	private function createMember(Workspace $workspace, string $name): User
	{
		$user = Fixture::createUser(name: $name);
		Fixture::addMember($workspace, $user, WorkspaceRoleEnum::Member);
		return $user;
	}

	private function createTask(User $author, int $projectId, string $name, int $assigneeId, string $dueDate): int
	{
		$response = $this->request(
			'POST',
			'/api/projects/' . $projectId . '/tasks',
			body: [
				'statusId' => $this->firstStatusId($projectId),
				'name' => $name,
				'description' => null,
				'priority' => 'Medium',
				'assigneeId' => $assigneeId,
				'dueDate' => $dueDate,
			],
			authenticatedAs: $author,
		);
		self::assertSame(200, $response->getStatusCode());
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
