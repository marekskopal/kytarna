<?php

declare(strict_types=1);

namespace Kytario\Tests\Service\Provider\Fake;

use ArrayIterator;
use DateTimeImmutable;
use Iterator;
use Kytario\Model\Entity\Enum\ActorTypeEnum;
use Kytario\Model\Entity\Enum\EventTypeEnum;
use Kytario\Model\Entity\Event;
use Kytario\Model\Entity\Project;
use Kytario\Model\Entity\User;
use Kytario\Model\Entity\Workspace;
use Kytario\Service\Provider\EventProviderInterface;

final class FakeEventProvider implements EventProviderInterface
{
	/** @var list<array{type: EventTypeEnum, metadata: array<string,mixed>, taskId: ?int}> */
	public array $recorded = [];

	public function recordEvent(User $author, Project $project, EventTypeEnum $type, array $metadata, ?int $taskId = null): Event
	{
		$this->recorded[] = ['type' => $type, 'metadata' => $metadata, 'taskId' => $taskId];
		$event = new Event(
			author: $author,
			type: $type,
			metadata: '{}',
			project: $project,
			taskId: $taskId,
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
	public function getEvents(Project $project, int $limit = 100, int $offset = 0): Iterator
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
		?int $projectId,
		?int $taskId,
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
