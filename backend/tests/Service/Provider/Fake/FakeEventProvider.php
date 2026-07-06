<?php

declare(strict_types=1);

namespace Kytarna\Tests\Service\Provider\Fake;

use ArrayIterator;
use DateTimeImmutable;
use Iterator;
use Kytarna\Model\Entity\Course;
use Kytarna\Model\Entity\Enum\ActorTypeEnum;
use Kytarna\Model\Entity\Enum\EventTypeEnum;
use Kytarna\Model\Entity\Event;
use Kytarna\Model\Entity\User;
use Kytarna\Model\Entity\Workspace;
use Kytarna\Service\Provider\EventProviderInterface;

final class FakeEventProvider implements EventProviderInterface
{
	/** @var list<array{type: EventTypeEnum, metadata: array<string,mixed>, lectureId: ?int}> */
	public array $recorded = [];

	public function recordEvent(User $author, Course $course, EventTypeEnum $type, array $metadata, ?int $lectureId = null): Event
	{
		$this->recorded[] = ['type' => $type, 'metadata' => $metadata, 'lectureId' => $lectureId];
		$event = new Event(
			author: $author,
			type: $type,
			metadata: '{}',
			course: $course,
			lectureId: $lectureId,
			actorType: ActorTypeEnum::Human,
		);
		$event->id = count($this->recorded);
		$event->createdAt = new DateTimeImmutable();
		$event->updatedAt = new DateTimeImmutable();
		return $event;
	}

	public function recordWorkspaceEvent(User $author, ?Workspace $workspace, EventTypeEnum $type, array $metadata): Event
	{
		$event = new Event(author: $author, type: $type, metadata: '{}', actorType: ActorTypeEnum::Human);
		$event->id = 1;
		$event->createdAt = new DateTimeImmutable();
		$event->updatedAt = new DateTimeImmutable();
		return $event;
	}

	/** @return Iterator<Event> */
	public function getEvents(Course $course, int $limit = 100, int $offset = 0): Iterator
	{
		return new ArrayIterator([]);
	}

	/** @return Iterator<Event> */
	public function getWorkspaceEvents(Workspace $workspace, ?ActorTypeEnum $actorType, int $limit, int $offset): Iterator
	{
		return new ArrayIterator([]);
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
		return new ArrayIterator([]);
	}

	public function countWorkspaceEventsSince(Workspace $workspace, int $sinceTimestamp): int
	{
		return 0;
	}

	public function countWorkspaceEventsOfTypeSince(Workspace $workspace, EventTypeEnum $type, int $sinceTimestamp): int
	{
		return 0;
	}
}
