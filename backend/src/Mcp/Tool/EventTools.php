<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use RuntimeException;
use Ukolio\Mcp\Dto\McpEventDto;
use Ukolio\Mcp\Dto\McpEventListDto;
use Ukolio\Mcp\McpUserContextInterface;
use Ukolio\Model\Entity\Enum\EventTypeEnum;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Service\Provider\EventProviderInterface;
use Ukolio\Service\Provider\TaskCodeResolverInterface;
use Ukolio\Service\Provider\WorkspaceProviderInterface;

final readonly class EventTools
{
	private const int DefaultLimit = 50;
	private const int MaxLimit = 200;

	public function __construct(
		private McpUserContextInterface $userContext,
		private WorkspaceProviderInterface $workspaceProvider,
		private EventProviderInterface $eventProvider,
		private TaskCodeResolverInterface $taskCodeResolver,
	) {
	}

	/**
	 * List audit-log events for the current workspace, newest first. Optionally narrow by project,
	 * task (numeric id), or event type. Use this to answer "when did X happen" — e.g. the latest
	 * `TaskMoved` event's createdAt tells you when a task entered its current status.
	 *
	 * @param int|null $projectId Optional: only events for this project
	 * @param int|null $taskId Optional: only events for this task (numeric id)
	 * @param string|null $type Optional: only events of this type (e.g. "TaskMoved", "TaskCreated")
	 * @param int $limit Max events to return (default 50, max 200)
	 * @param int $offset Pagination offset
	 */
	#[McpTool(
		name: 'list_events',
		description: 'List workspace audit-log events (newest first), optionally filtered by projectId, taskId, or type. '
			. 'Event createdAt is ISO 8601; TaskMoved metadata carries toStatusId/toStatusName so you can tell when a task entered a status.',
	)]
	public function listEvents(
		?int $projectId = null,
		?int $taskId = null,
		?string $type = null,
		int $limit = self::DefaultLimit,
		int $offset = 0,
	): McpEventListDto {
		$workspace = $this->requireWorkspace();

		return $this->collect($workspace, $projectId, $taskId, $this->resolveType($type), $limit, $offset);
	}

	/**
	 * List events for a single task by numeric id or code (e.g. "U-45"), newest first. Convenience
	 * wrapper over list_events that accepts a task code.
	 *
	 * @param int|string $taskId Task numeric id or code (e.g. "U-45")
	 * @param string|null $type Optional: only events of this type
	 * @param int $limit Max events to return (default 50, max 200)
	 * @param int $offset Pagination offset
	 */
	#[McpTool(
		name: 'list_task_events',
		description: 'List audit-log events for a single task (by id or code), newest first. Optionally filtered by type.',
	)]
	public function listTaskEvents(
		int|string $taskId,
		?string $type = null,
		int $limit = self::DefaultLimit,
		int $offset = 0,
	): McpEventListDto {
		$workspace = $this->requireWorkspace();

		$task = $this->taskCodeResolver->resolveForUser($this->userContext->getUser(), (string) $taskId)
			?? throw new RuntimeException(sprintf('Task "%s" not found.', (string) $taskId));

		return $this->collect($workspace, null, $task->id, $this->resolveType($type), $limit, $offset);
	}

	private function collect(
		Workspace $workspace,
		?int $projectId,
		?int $taskId,
		?EventTypeEnum $type,
		int $limit,
		int $offset,
	): McpEventListDto {
		$boundedLimit = min(max($limit, 1), self::MaxLimit);
		$boundedOffset = max($offset, 0);

		$events = [];
		foreach ($this->eventProvider->getWorkspaceEventsFiltered(
			$workspace,
			$projectId,
			$taskId,
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
			throw new RuntimeException('No active workspace. Create one in the Ukolio app first.');
		}

		return $workspace;
	}
}
