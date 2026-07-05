<?php

declare(strict_types=1);

namespace Kytario\Mcp\Tool;

use Kytario\Mcp\Dto\McpEventDto;
use Kytario\Mcp\Dto\McpEventListDto;
use Kytario\Mcp\McpUserContextInterface;
use Kytario\Model\Entity\Enum\EventTypeEnum;
use Kytario\Model\Entity\Workspace;
use Kytario\Service\Provider\EventProviderInterface;
use Kytario\Service\Provider\LectureCodeResolverInterface;
use Kytario\Service\Provider\WorkspaceProviderInterface;
use Mcp\Capability\Attribute\McpTool;
use RuntimeException;

final readonly class EventTools
{
	private const int DefaultLimit = 50;
	private const int MaxLimit = 200;

	public function __construct(
		private McpUserContextInterface $userContext,
		private WorkspaceProviderInterface $workspaceProvider,
		private EventProviderInterface $eventProvider,
		private LectureCodeResolverInterface $lectureCodeResolver,
	) {
	}

	/**
	 * List audit-log events for the current workspace, newest first. Optionally narrow by course,
	 * lecture (numeric id), or event type. Use this to answer "when did X happen" — e.g. the latest
	 * `LectureMoved` event's createdAt tells you when a lecture entered its current status.
	 *
	 * @param int|null $courseId Optional: only events for this course
	 * @param int|null $lectureId Optional: only events for this lecture (numeric id)
	 * @param string|null $type Optional: only events of this type (e.g. "LectureMoved", "LectureCreated")
	 * @param int $limit Max events to return (default 50, max 200)
	 * @param int $offset Pagination offset
	 */
	#[McpTool(
		name: 'list_events',
		description: 'List workspace audit-log events (newest first), optionally filtered by courseId, lectureId, or type. '
			. 'Event createdAt is ISO 8601; LectureMoved metadata carries toStatusId/toStatusName so you can tell when a lecture entered a status.',
	)]
	public function listEvents(
		?int $courseId = null,
		?int $lectureId = null,
		?string $type = null,
		int $limit = self::DefaultLimit,
		int $offset = 0,
	): McpEventListDto {
		$workspace = $this->requireWorkspace();

		return $this->collect($workspace, $courseId, $lectureId, $this->resolveType($type), $limit, $offset);
	}

	/**
	 * List events for a single lecture by numeric id or code (e.g. "U-45"), newest first. Convenience
	 * wrapper over list_events that accepts a lecture code.
	 *
	 * @param int|string $lectureId Lecture numeric id or code (e.g. "U-45")
	 * @param string|null $type Optional: only events of this type
	 * @param int $limit Max events to return (default 50, max 200)
	 * @param int $offset Pagination offset
	 */
	#[McpTool(
		name: 'list_lecture_events',
		description: 'List audit-log events for a single lecture (by id or code), newest first. Optionally filtered by type.',
	)]
	public function listLectureEvents(
		int|string $lectureId,
		?string $type = null,
		int $limit = self::DefaultLimit,
		int $offset = 0,
	): McpEventListDto {
		$workspace = $this->requireWorkspace();

		$lecture = $this->lectureCodeResolver->resolveForUser($this->userContext->getUser(), (string) $lectureId)
			?? throw new RuntimeException(sprintf('Lecture "%s" not found.', (string) $lectureId));

		return $this->collect($workspace, null, $lecture->id, $this->resolveType($type), $limit, $offset);
	}

	private function collect(
		Workspace $workspace,
		?int $courseId,
		?int $lectureId,
		?EventTypeEnum $type,
		int $limit,
		int $offset,
	): McpEventListDto {
		$boundedLimit = min(max($limit, 1), self::MaxLimit);
		$boundedOffset = max($offset, 0);

		$events = [];
		foreach ($this->eventProvider->getWorkspaceEventsFiltered(
			$workspace,
			$courseId,
			$lectureId,
			$type,
			$boundedLimit,
			$boundedOffset,
		) as $event) {
			$events[] = McpEventDto::fromEntity($event);
		}

		return new McpEventListDto($events);
	}

	private function resolveType(?string $type): ?EventTypeEnum
	{
		if ($type === null || $type === '') {
			return null;
		}

		return EventTypeEnum::tryFrom($type)
			?? throw new RuntimeException(sprintf('Unknown event type "%s".', $type));
	}

	private function requireWorkspace(): Workspace
	{
		$workspace = $this->workspaceProvider->getCurrentWorkspace($this->userContext->getUser());
		if ($workspace === null) {
			throw new RuntimeException('No active workspace. Create one in the Kytario app first.');
		}

		return $workspace;
	}
}
