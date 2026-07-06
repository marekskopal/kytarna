<?php

declare(strict_types=1);

namespace Kytarna\Tests\Service\Notification;

use DateTimeImmutable;
use Kytarna\Model\Entity\Course;
use Kytarna\Model\Entity\Enum\ActorTypeEnum;
use Kytarna\Model\Entity\Enum\EventTypeEnum;
use Kytarna\Model\Entity\Enum\WorkspaceRoleEnum;
use Kytarna\Model\Entity\Event;
use Kytarna\Model\Entity\Lecture;
use Kytarna\Model\Entity\Notification;
use Kytarna\Model\Entity\User;
use Kytarna\Model\Entity\Workspace;
use Kytarna\Model\Repository\LectureRepository;
use Kytarna\Service\Notification\NotificationDispatcher;
use Kytarna\Service\Notification\NotificationDispatcherInterface;
use Kytarna\Service\Provider\LectureWatcherProviderInterface;
use Kytarna\Service\Provider\NotificationProviderInterface;
use Kytarna\Tests\Support\Fixture;
use Kytarna\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use const JSON_THROW_ON_ERROR;

#[CoversClass(NotificationDispatcher::class)]
final class NotificationDispatcherTest extends IntegrationTestCase
{
	public function testHumanMoveNotifiesWatchersButAgentMoveIsSuppressed(): void
	{
		$owner = Fixture::createUser(name: 'Owner');
		$workspace = Fixture::createWorkspace($owner);
		$bob = $this->createMember($workspace, 'Bob');
		$course = Fixture::createCourse($owner, $workspace);

		$lecture = $this->createLecture($owner, $course->id, 'Movable lecture');
		$this->watcherProvider()->watch($lecture, $bob);

		// An agent-driven move must not ping watchers (agents churn statuses).
		$this->dispatcher()->onEvent($this->moveEvent($owner, $workspace, $course, $lecture, ActorTypeEnum::Agent));
		$afterAgentMove = $this->notificationsFor($bob);
		self::assertCount(0, $afterAgentMove);

		// A human-authored move event pings the watcher (Bob), but not the actor (owner).
		// (Board card drags now record personal progress rather than a shared LectureMoved event;
		// LectureMoved events still come from teacher template ops such as bulk updates and MCP.)
		$this->dispatcher()->onEvent($this->moveEvent($owner, $workspace, $course, $lecture, ActorTypeEnum::Human));

		$ownerNotifications = $this->notificationsFor($owner);
		self::assertCount(0, $ownerNotifications);

		$bobNotifications = $this->notificationsFor($bob);
		self::assertCount(1, $bobNotifications);

		// The stored notification carries the destination status (enum value) under the `status` data key.
		$data = json_decode((string) $bobNotifications[0]->data, true, flags: JSON_THROW_ON_ERROR);
		self::assertIsArray($data);
		self::assertSame('Learning', $data['status']);
	}

	private function moveEvent(User $author, Workspace $workspace, Course $course, Lecture $lecture, ActorTypeEnum $actorType): Event
	{
		$event = new Event(
			author: $author,
			type: EventTypeEnum::LectureMoved,
			metadata: json_encode(['toStatus' => 'Learning', 'lectureName' => $lecture->name], JSON_THROW_ON_ERROR),
			course: $course,
			workspaceId: $workspace->id,
			lectureId: $lecture->id,
			actorType: $actorType,
		);
		$event->createdAt = new DateTimeImmutable();
		$event->updatedAt = new DateTimeImmutable();
		return $event;
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

	private function watcherProvider(): LectureWatcherProviderInterface
	{
		$provider = $this->container->get(LectureWatcherProviderInterface::class);
		assert($provider instanceof LectureWatcherProviderInterface);
		return $provider;
	}

	private function createMember(Workspace $workspace, string $name): User
	{
		$user = Fixture::createUser(name: $name);
		Fixture::addMember($workspace, $user, WorkspaceRoleEnum::Student);
		return $user;
	}

	private function createLecture(User $author, int $courseId, string $name): Lecture
	{
		$body = ['status' => 'ToLearn', 'name' => $name, 'description' => null];

		$response = $this->request('POST', '/api/courses/' . $courseId . '/lectures', body: $body, authenticatedAs: $author);
		self::assertSame(200, $response->getStatusCode());

		return $this->lecture(self::intField($this->jsonBody($response)['id']));
	}

	private function lecture(int $lectureId): Lecture
	{
		$lectureRepository = $this->container->get(LectureRepository::class);
		assert($lectureRepository instanceof LectureRepository);
		$lecture = $lectureRepository->findById($lectureId);
		assert($lecture instanceof Lecture);
		return $lecture;
	}
}
