<?php

declare(strict_types=1);

namespace Kytarna\Service\Provider;

use Iterator;
use Kytarna\Model\Entity\Course;
use Kytarna\Model\Entity\Enum\ActorTypeEnum;
use Kytarna\Model\Entity\Enum\EventTypeEnum;
use Kytarna\Model\Entity\Event;
use Kytarna\Model\Entity\User;
use Kytarna\Model\Entity\Workspace;

interface EventProviderInterface
{
	/** @param array<string,mixed> $metadata */
	public function recordEvent(User $author, Course $course, EventTypeEnum $type, array $metadata, ?int $lectureId = null): Event;

	/** @param array<string,mixed> $metadata */
	public function recordWorkspaceEvent(User $author, ?Workspace $workspace, EventTypeEnum $type, array $metadata): Event;

	/** @return Iterator<Event> */
	public function getEvents(Course $course, int $limit = 100, int $offset = 0): Iterator;

	/** @return Iterator<Event> */
	public function getWorkspaceEvents(Workspace $workspace, ?ActorTypeEnum $actorType, int $limit, int $offset): Iterator;

	/**
	 * Workspace-scoped events with optional course/lecture/type filters (newest first).
	 *
	 * @return Iterator<Event>
	 */
	public function getWorkspaceEventsFiltered(
		Workspace $workspace,
		?int $courseId,
		?int $lectureId,
		?EventTypeEnum $type,
		int $limit,
		int $offset,
	): Iterator;

	public function countWorkspaceEventsSince(Workspace $workspace, int $sinceTimestamp): int;

	public function countWorkspaceEventsOfTypeSince(Workspace $workspace, EventTypeEnum $type, int $sinceTimestamp): int;
}
