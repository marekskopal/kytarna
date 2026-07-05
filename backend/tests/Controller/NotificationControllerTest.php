<?php

declare(strict_types=1);

namespace Ukolio\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use Ukolio\Controller\NotificationController;
use Ukolio\Model\Entity\Enum\WorkspaceRoleEnum;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Model\Repository\StatusRepository;
use Ukolio\Model\Repository\WorkflowRepository;
use Ukolio\Tests\Support\Fixture;
use Ukolio\Tests\Support\IntegrationTestCase;

#[CoversClass(NotificationController::class)]
final class NotificationControllerTest extends IntegrationTestCase
{
	public function testListUnreadCountMarkReadAndDelete(): void
	{
		$owner = Fixture::createUser(name: 'Owner');
		$workspace = Fixture::createWorkspace($owner);
		$bob = $this->createMember($workspace, 'Bob');
		$project = Fixture::createProject($owner, $workspace);

		// Assigning a task to Bob creates a TaskAssigned notification for him.
		$this->createTask($owner, $project->id, 'Task one', $bob->id);

		$list = $this->jsonBody($this->request('GET', '/api/notifications', authenticatedAs: $bob));
		self::assertSame(1, $list['unreadCount']);
		$items = $this->items($list);
		self::assertCount(1, $items);
		self::assertSame('TaskAssigned', $items[0]['type']);
		self::assertFalse($items[0]['read']);
		$notificationId = self::intField($items[0]['id']);

		self::assertSame(1, $this->unreadCount($bob));

		$read = $this->jsonBody($this->request('POST', '/api/notifications/' . $notificationId . '/read', authenticatedAs: $bob));
		self::assertTrue($read['read']);
		self::assertSame(0, $this->unreadCount($bob));

		$this->createTask($owner, $project->id, 'Task two', $bob->id);
		self::assertSame(1, $this->unreadCount($bob));

		$marked = $this->jsonBody($this->request('POST', '/api/notifications/read-all', authenticatedAs: $bob));
		self::assertSame(1, $marked['marked']);
		self::assertSame(0, $this->unreadCount($bob));

		$delete = $this->request('DELETE', '/api/notifications/' . $notificationId, authenticatedAs: $bob);
		self::assertSame(200, $delete->getStatusCode());
		self::assertCount(1, $this->items($this->jsonBody($this->request('GET', '/api/notifications', authenticatedAs: $bob))));
	}

	public function testUnreadOnlyFilter(): void
	{
		$owner = Fixture::createUser(name: 'Owner');
		$workspace = Fixture::createWorkspace($owner);
		$bob = $this->createMember($workspace, 'Bob');
		$project = Fixture::createProject($owner, $workspace);
		$this->createTask($owner, $project->id, 'Task', $bob->id);

		$items = $this->items($this->jsonBody($this->request('GET', '/api/notifications', authenticatedAs: $bob)));
		$this->request('POST', '/api/notifications/' . self::intField($items[0]['id']) . '/read', authenticatedAs: $bob);
		$this->createTask($owner, $project->id, 'Task two', $bob->id);

		$unread = $this->items($this->jsonBody($this->request('GET', '/api/notifications?unreadOnly=1', authenticatedAs: $bob)));
		self::assertCount(1, $unread);
	}

	public function testCannotTouchAnotherUsersNotification(): void
	{
		$owner = Fixture::createUser(name: 'Owner');
		$workspace = Fixture::createWorkspace($owner);
		$bob = $this->createMember($workspace, 'Bob');
		$project = Fixture::createProject($owner, $workspace);
		$this->createTask($owner, $project->id, 'Task', $bob->id);

		$items = $this->items($this->jsonBody($this->request('GET', '/api/notifications', authenticatedAs: $bob)));
		$id = self::intField($items[0]['id']);

		self::assertSame(404, $this->request('POST', '/api/notifications/' . $id . '/read', authenticatedAs: $owner)->getStatusCode());
		self::assertSame(404, $this->request('DELETE', '/api/notifications/' . $id, authenticatedAs: $owner)->getStatusCode());
	}

	/**
	 * @param array<string, mixed> $body
	 * @return list<array<array-key, mixed>>
	 */
	private function items(array $body): array
	{
		$items = $body['notifications'];
		self::assertIsArray($items);
		$result = [];
		foreach ($items as $item) {
			self::assertIsArray($item);
			$result[] = $item;
		}
		return $result;
	}

	private function unreadCount(User $user): int
	{
		return self::intField(
			$this->jsonBody($this->request('GET', '/api/notifications/unread-count', authenticatedAs: $user))['unreadCount'],
		);
	}

	private function createMember(Workspace $workspace, string $name): User
	{
		$user = Fixture::createUser(name: $name);
		Fixture::addMember($workspace, $user, WorkspaceRoleEnum::Member);
		return $user;
	}

	private function createTask(User $author, int $projectId, string $name, int $assigneeId): void
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
			],
			authenticatedAs: $author,
		);
		self::assertSame(200, $response->getStatusCode());
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
