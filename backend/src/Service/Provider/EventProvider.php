<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use DateTimeImmutable;
use Iterator;
use Kytarna\Model\Entity\Course;
use Kytarna\Model\Entity\Enum\ActorTypeEnum;
use Kytarna\Model\Entity\Enum\EventTypeEnum;
use Kytarna\Model\Entity\Event;
use Kytarna\Model\Entity\User;
use Kytarna\Model\Entity\Workspace;
use Kytarna\Model\Repository\EventRepository;
use Kytarna\Service\Actor\ActorContextInterface;
use Kytarna\Service\Notification\NotificationDispatcherInterface;
use const JSON_THROW_ON_ERROR;

final readonly class EventProvider implements EventProviderInterface
{
	public function __construct(
		private EventRepository $eventRepository,
		private ActorContextInterface $actorContext,
		private NotificationDispatcherInterface $notificationDispatcher,
	) {
	}

	/** @param array<string,mixed> $metadata */
	public function recordEvent(User $author, Course $course, EventTypeEnum $type, array $metadata, ?int $lectureId = null): Event
	{
		$now = new DateTimeImmutable();
		$event = new Event(
			author: $author,
			type: $type,
			metadata: json_encode($metadata, JSON_THROW_ON_ERROR),
			course: $course,
			workspaceId: $course->workspace->id,
			lectureId: $lectureId,
			actorType: $this->actorContext->getActorType(),
			mcpClientId: $this->actorContext->getMcpClientId(),
			mcpClientName: $this->actorContext->getMcpClientName(),
		);
		$event->createdAt = $now;
		$event->updatedAt = $now;

		$this->eventRepository->persist($event);

		$this->notificationDispatcher->onEvent($event);

		return $event;
	}

	/** @param array<string,mixed> $metadata */
	public function recordWorkspaceEvent(User $author, ?Workspace $workspace, EventTypeEnum $type, array $metadata): Event
	{
		$now = new DateTimeImmutable();
		$event = new Event(
			author: $author,
			type: $type,
			metadata: json_encode($metadata, JSON_THROW_ON_ERROR),
			course: null,
			workspaceId: $workspace?->id,
			actorType: $this->actorContext->getActorType(),
			mcpClientId: $this->actorContext->getMcpClientId(),
			mcpClientName: $this->actorContext->getMcpClientName(),
		);
		$event->createdAt = $now;
		$event->updatedAt = $now;

		$this->eventRepository->persist($event);

		return $event;
	}

	/** @return Iterator<Event> */
	public function getEvents(Course $course, int $limit = 100, int $offset = 0): Iterator
	{
		return $this->eventRepository->findByCourse($course->id, $limit, $offset);
	}

	/** @return Iterator<Event> */
	public function getWorkspaceEvents(Workspace $workspace, ?ActorTypeEnum $actorType, int $limit, int $offset): Iterator
	{
		return $this->eventRepository->findByWorkspace($workspace->id, $actorType, $limit, $offset);
	}

	/** @return Iterator<Event> */
	public function getWorkspaceEventsFiltered(
		Workspace $workspace,
		?int $courseId,
		?int $lectureId,
		?EventTypeEnum $type,
		int $limit,
		int $offset,
	): Iterator {
		return $this->eventRepository->findByWorkspaceFiltered($workspace->id, $courseId, $lectureId, $type, $limit, $offset);
	}

	public function countWorkspaceEventsSince(Workspace $workspace, int $sinceTimestamp): int
	{
		return $this->eventRepository->countByWorkspaceSince($workspace->id, $sinceTimestamp);
	}

	public function countWorkspaceEventsOfTypeSince(Workspace $workspace, EventTypeEnum $type, int $sinceTimestamp): int
	{
		return $this->eventRepository->countByWorkspaceTypeSince($workspace->id, $type, $sinceTimestamp);
	}
}
