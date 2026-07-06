<?php

declare(strict_types=1);

namespace Kytarna\Tests\Controller;

use Kytarna\Controller\NotificationController;
use Kytarna\Model\Entity\Enum\NotificationTypeEnum;
use Kytarna\Model\Entity\Enum\WorkspaceRoleEnum;
use Kytarna\Model\Entity\User;
use Kytarna\Model\Entity\Workspace;
use Kytarna\Service\Provider\NotificationProviderInterface;
use Kytarna\Service\Provider\WorkspaceProviderInterface;
use Kytarna\Tests\Support\Fixture;
use Kytarna\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(NotificationController::class)]
final class NotificationControllerTest extends IntegrationTestCase
{
	public function testListUnreadCountMarkReadAndDelete(): void
	{
		$owner = Fixture::createUser(name: 'Owner');
		$workspace = Fixture::createWorkspace($owner);
		$bob = $this->createMember($workspace, 'Bob');
		$course = Fixture::createCourse($owner, $workspace);

		// Moving a lecture watched by Bob creates a LectureMoved notification for him.
		$this->createWatchedAndMovedLecture($owner, $bob, $course->id, 'Lecture one');

		$list = $this->jsonBody($this->request('GET', '/api/notifications', authenticatedAs: $bob));
		self::assertSame(1, $list['unreadCount']);
		$items = $this->items($list);
		self::assertCount(1, $items);
		self::assertSame('LectureMoved', $items[0]['type']);
		self::assertFalse($items[0]['read']);
		$notificationId = self::intField($items[0]['id']);

		self::assertSame(1, $this->unreadCount($bob));

		$read = $this->jsonBody($this->request('POST', '/api/notifications/' . $notificationId . '/read', authenticatedAs: $bob));
		self::assertTrue($read['read']);
		self::assertSame(0, $this->unreadCount($bob));

		$this->createWatchedAndMovedLecture($owner, $bob, $course->id, 'Lecture two');
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
		$course = Fixture::createCourse($owner, $workspace);
		$this->createWatchedAndMovedLecture($owner, $bob, $course->id, 'Lecture');

		$items = $this->items($this->jsonBody($this->request('GET', '/api/notifications', authenticatedAs: $bob)));
		$this->request('POST', '/api/notifications/' . self::intField($items[0]['id']) . '/read', authenticatedAs: $bob);
		$this->createWatchedAndMovedLecture($owner, $bob, $course->id, 'Lecture two');

		$unread = $this->items($this->jsonBody($this->request('GET', '/api/notifications?unreadOnly=1', authenticatedAs: $bob)));
		self::assertCount(1, $unread);
	}

	public function testCannotTouchAnotherUsersNotification(): void
	{
		$owner = Fixture::createUser(name: 'Owner');
		$workspace = Fixture::createWorkspace($owner);
		$bob = $this->createMember($workspace, 'Bob');
		$course = Fixture::createCourse($owner, $workspace);
		$this->createWatchedAndMovedLecture($owner, $bob, $course->id, 'Lecture');

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
		Fixture::addMember($workspace, $user, WorkspaceRoleEnum::Student);
		return $user;
	}

	/**
	 * Creates a lecture, lets the watcher start watching it, then seeds a LectureMoved notification
	 * for the watcher. Board card drags now record personal progress (no shared move event), so the
	 * notification is seeded directly to exercise the notification inbox controller.
	 */
	private function createWatchedAndMovedLecture(User $author, User $watcher, int $courseId, string $name): void
	{
		$response = $this->request(
			'POST',
			'/api/courses/' . $courseId . '/lectures',
			body: [
				'status' => 'ToLearn',
				'name' => $name,
				'description' => null,
			],
			authenticatedAs: $author,
		);
		self::assertSame(200, $response->getStatusCode());
		$lectureId = self::intField($this->jsonBody($response)['id']);

		$watch = $this->request('POST', '/api/lectures/' . $lectureId . '/watch', authenticatedAs: $watcher);
		self::assertSame(200, $watch->getStatusCode());

		$notificationProvider = $this->container->get(NotificationProviderInterface::class);
		assert($notificationProvider instanceof NotificationProviderInterface);
		$workspaceProvider = $this->container->get(WorkspaceProviderInterface::class);
		assert($workspaceProvider instanceof WorkspaceProviderInterface);
		$workspace = $workspaceProvider->getCurrentWorkspace($author);
		assert($workspace !== null);

		$notificationProvider->create(
			$watcher,
			$workspace->id,
			NotificationTypeEnum::LectureMoved,
			$lectureId,
			$courseId,
			$author->id,
			$author->name,
			['status' => 'Learning'],
		);
	}
}
