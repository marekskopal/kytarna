<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use DateTimeImmutable;
use Iterator;
use Ukolio\Model\Entity\Enum\ActorTypeEnum;
use Ukolio\Model\Entity\Enum\EventTypeEnum;
use Ukolio\Model\Entity\Event;
use Ukolio\Model\Entity\Project;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Model\Repository\EventRepository;
use Ukolio\Service\Actor\ActorContextInterface;
use Ukolio\Service\Notification\NotificationDispatcherInterface;
use Ukolio\Service\Realtime\RealtimePublisherInterface;
use Ukolio\Service\Recurrence\RecurrenceTriggerInterface;
use Ukolio\Service\Script\Trigger\ScriptEventTriggerInterface;
use const JSON_THROW_ON_ERROR;

final readonly class EventProvider implements EventProviderInterface
{
	public function __construct(
		private EventRepository $eventRepository,
		private ActorContextInterface $actorContext,
		private RealtimePublisherInterface $realtimePublisher,
		private ScriptEventTriggerInterface $scriptEventTrigger,
		private NotificationDispatcherInterface $notificationDispatcher,
		private RecurrenceTriggerInterface $recurrenceTrigger,
	) {
	}

	/** @param array<string,mixed> $metadata */
	public function recordEvent(User $author, Project $project, EventTypeEnum $type, array $metadata, ?int $taskId = null): Event
	{
		$now = new DateTimeImmutable();
		$event = new Event(
			author: $author,
			type: $type,
			metadata: json_encode($metadata, JSON_THROW_ON_ERROR),
			project: $project,
			workspaceId: $project->workspace->id,
			taskId: $taskId,
			actorType: $this->actorContext->getActorType(),
			mcpClientId: $this->actorContext->getMcpClientId(),
			mcpClientName: $this->actorContext->getMcpClientName(),
		);
		$event->createdAt = $now;
		$event->updatedAt = $now;

		$this->eventRepository->persist($event);

		$this->realtimePublisher->publish(
			type: $type,
			workspaceId: $project->workspace->id,
			projectId: $project->id,
			taskId: $taskId,
			commentId: $this->intFromMetadata($metadata, 'commentId'),
			fileId: $this->intFromMetadata($metadata, 'fileId'),
			relationId: $this->intFromMetadata($metadata, 'relationId'),
		);

		$this->scriptEventTrigger->onEvent($event);
		$this->notificationDispatcher->onEvent($event);
		$this->recurrenceTrigger->onEvent($event);

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
			project: null,
			workspaceId: $workspace?->id,
			actorType: $this->actorContext->getActorType(),
			mcpClientId: $this->actorContext->getMcpClientId(),
			mcpClientName: $this->actorContext->getMcpClientName(),
		);
		$event->createdAt = $now;
		$event->updatedAt = $now;

		$this->eventRepository->persist($event);

		if ($workspace !== null) {
			$this->realtimePublisher->publish(type: $type, workspaceId: $workspace->id);
		}

		return $event;
	}

	/** @param array<string,mixed> $metadata */
	private function intFromMetadata(array $metadata, string $key): ?int
	{
		$value = $metadata[$key] ?? null;
		return is_int($value) ? $value : null;
	}

	/** @return Iterator<Event> */
	public function getEvents(Project $project, int $limit = 100, int $offset = 0): Iterator
	{
		return $this->eventRepository->findByProject($project->id, $limit, $offset);
	}

	/** @return Iterator<Event> */
	public function getWorkspaceEvents(Workspace $workspace, ?ActorTypeEnum $actorType, int $limit, int $offset): Iterator
	{
		return $this->eventRepository->findByWorkspace($workspace->id, $actorType, $limit, $offset);
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
		return $this->eventRepository->findByWorkspaceFiltered($workspace->id, $projectId, $taskId, $type, $limit, $offset);
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
