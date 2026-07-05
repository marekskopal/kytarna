<?php

declare(strict_types=1);

namespace Kytario\Tests\Service\Notification;

use DateTimeImmutable;
use Kytario\Model\Entity\Course;
use Kytario\Model\Entity\Enum\ActorTypeEnum;
use Kytario\Model\Entity\Enum\EventTypeEnum;
use Kytario\Model\Entity\Enum\WorkspaceRoleEnum;
use Kytario\Model\Entity\Event;
use Kytario\Model\Entity\Lecture;
use Kytario\Model\Entity\Notification;
use Kytario\Model\Entity\User;
use Kytario\Model\Entity\Workspace;
use Kytario\Model\Repository\LectureRepository;
use Kytario\Model\Repository\StatusRepository;
use Kytario\Model\Repository\WorkflowRepository;
use Kytario\Service\Notification\NotificationDispatcher;
use Kytario\Service\Notification\NotificationDispatcherInterface;
use Kytario\Service\Provider\LectureWatcherProviderInterface;
use Kytario\Service\Provider\NotificationProviderInterface;
use Kytario\Tests\Support\Fixture;
use Kytario\Tests\Support\IntegrationTestCase;
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
		$lectureId = $lecture->id;
		$this->watcherProvider()->watch($lecture, $bob);

		// An agent-driven move must not ping watchers (agents churn statuses).
		$this->dispatcher()->onEvent($this->moveEvent($owner, $workspace, $course, $lecture, ActorTypeEnum::Agent));
		self::assertCount(0, $this->notificationsFor($bob));

		// A human move pings the watcher (Bob), but not the actor (owner).
		$secondStatusId = $this->statusIdAtIndex($course->id, 1);
		$this->request(
			'PUT',
			'/api/lectures/' . $lectureId . '/move',
			body: ['statusId' => $secondStatusId, 'position' => 0],
			authenticatedAs: $owner,
		);

		self::assertCount(1, $this->notificationsFor($bob));
		self::assertCount(0, $this->notificationsFor($owner));
	}

	private function moveEvent(User $author, Workspace $workspace, Course $course, Lecture $lecture, ActorTypeEnum $actorType): Event
	{
		$event = new Event(
			author: $author,
			type: EventTypeEnum::LectureMoved,
			metadata: json_encode(['toStatusName' => 'Learning', 'lectureName' => $lecture->name], JSON_THROW_ON_ERROR),
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
		Fixture::addMember($workspace, $user, WorkspaceRoleEnum::Member);
		return $user;
	}

	private function createLecture(User $author, int $courseId, string $name): Lecture
	{
		$body = ['statusId' => $this->statusIdAtIndex($courseId, 0), 'name' => $name, 'description' => null];

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

	private function statusIdAtIndex(int $courseId, int $index): int
	{
		$workflowRepo = $this->container->get(WorkflowRepository::class);
		assert($workflowRepo instanceof WorkflowRepository);
		$workflow = $workflowRepo->findByCourse($courseId);
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
