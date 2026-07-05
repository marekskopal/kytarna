<?php

declare(strict_types=1);

namespace Kytario\Service\Provider;

use Iterator;
use Kytario\Model\Entity\Enum\ActorTypeEnum;
use Kytario\Model\Entity\Enum\EventTypeEnum;
use Kytario\Model\Entity\Event;
use Kytario\Model\Entity\Project;
use Kytario\Model\Entity\User;
use Kytario\Model\Entity\Workspace;

interface EventProviderInterface
{
	/** @param array<string,mixed> $metadata */
	public function recordEvent(User $author, Project $project, EventTypeEnum $type, array $metadata, ?int $taskId = null): Event;

	/** @param array<string,mixed> $metadata */
	public function recordWorkspaceEvent(User $author, ?Workspace $workspace, EventTypeEnum $type, array $metadata): Event;

	/** @return Iterator<Event> */
	public function getEvents(Project $project, int $limit = 100, int $offset = 0): Iterator;

	/** @return Iterator<Event> */
	public function getWorkspaceEvents(Workspace $workspace, ?ActorTypeEnum $actorType, int $limit, int $offset): Iterator;

	/**
	 * Workspace-scoped events with optional project/task/type filters (newest first).
	 *
	 * @return Iterator<Event>
	 */
	public function getWorkspaceEventsFiltered(
		Workspace $workspace,
		?int $projectId,
		?int $taskId,
		?EventTypeEnum $type,
		int $limit,
		int $offset,
	): Iterator;

	public function countWorkspaceEventsSince(Workspace $workspace, int $sinceTimestamp): int;

	public function countWorkspaceEventsOfTypeSince(Workspace $workspace, EventTypeEnum $type, int $sinceTimestamp): int;
}
