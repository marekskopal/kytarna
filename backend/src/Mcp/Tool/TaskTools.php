<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Tool;

use DateTimeImmutable;
use Mcp\Capability\Attribute\McpTool;
use RuntimeException;
use Ukolio\Dto\DateInput;
use Ukolio\Dto\TaskRecurrenceWriteDto;
use Ukolio\Mcp\Dto\McpTaskDto;
use Ukolio\Mcp\Dto\McpTaskListDto;
use Ukolio\Mcp\McpUserContextInterface;
use Ukolio\Mcp\Tool\Helper\PriorityResolver;
use Ukolio\Mcp\Tool\Helper\StatusResolver;
use Ukolio\Model\Entity\Enum\StatusTypeEnum;
use Ukolio\Model\Entity\Project;
use Ukolio\Model\Entity\Task;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Model\Repository\UserRepository;
use Ukolio\Service\Provider\BulkTaskProviderInterface;
use Ukolio\Service\Provider\Enum\BulkOpEnum;
use Ukolio\Service\Provider\ProjectProviderInterface;
use Ukolio\Service\Provider\StatusProviderInterface;
use Ukolio\Service\Provider\TaskCodeResolverInterface;
use Ukolio\Service\Provider\TaskFieldValueProviderInterface;
use Ukolio\Service\Provider\TaskProviderInterface;
use Ukolio\Service\Provider\TaskRecurrenceProviderInterface;
use Ukolio\Service\Provider\TaskTagProviderInterface;
use Ukolio\Service\Provider\WorkspaceProviderInterface;

final readonly class TaskTools
{
	public function __construct(
		private McpUserContextInterface $userContext,
		private ProjectProviderInterface $projectProvider,
		private StatusProviderInterface $statusProvider,
		private TaskProviderInterface $taskProvider,
		private TaskCodeResolverInterface $taskCodeResolver,
		private WorkspaceProviderInterface $workspaceProvider,
		private TaskFieldValueProviderInterface $taskFieldValueProvider,
		private TaskTagProviderInterface $taskTagProvider,
		private PriorityResolver $priorityResolver,
		private StatusResolver $statusResolver,
		private UserRepository $userRepository,
		private BulkTaskProviderInterface $bulkTaskProvider,
		private TaskRecurrenceProviderInterface $recurrenceProvider,
	) {
	}

	/**
	 * List all tasks in a project, ordered by status then position. Optionally filter by status.
	 * Archived tasks are hidden by default; pass includeArchived=true to include them.
	 *
	 * @param int $projectId Project ID
	 * @param int|null $statusId Optional: only return tasks in this status
	 * @param bool $includeArchived Include archived tasks (default false)
	 */
	#[McpTool(name: 'list_tasks', description: 'List tasks in a project, optionally filtered by status. Hides archived tasks by default.')]
	public function listTasks(int $projectId, ?int $statusId = null, bool $includeArchived = false): McpTaskListDto
	{
		$project = $this->requireProject($projectId);

		$tasks = [];
		foreach ($this->taskProvider->getTasksByProject($project, $includeArchived) as $task) {
			if ($statusId !== null && $task->status->id !== $statusId) {
				continue;
			}
			$tasks[] = McpTaskDto::fromEntity(
				$task,
				$this->taskFieldValueProvider->findByTask($task),
				$this->taskTagProvider->getTagIdsForTask($task),
			);
		}

		return new McpTaskListDto($tasks);
	}

	/**
	 * Find a task by case-insensitive name match within a project. Returns the first match,
	 * preferring exact matches over substring matches.
	 *
	 * @param int $projectId Project ID
	 * @param string $name Task name to search for
	 */
	#[McpTool(
		name: 'find_task_by_name',
		description: 'Find a task in a project by name (case-insensitive). Prefers exact matches over substring matches.',
	)]
	public function findTaskByName(int $projectId, string $name): ?McpTaskDto
	{
		$project = $this->requireProject($projectId);
		$needle = mb_strtolower($name);

		$exact = null;
		$partial = null;
		foreach ($this->taskProvider->getTasksByProject($project) as $task) {
			$haystack = mb_strtolower($task->name);
			if ($haystack === $needle) {
				$exact = $task;
				break;
			}
			if ($partial === null && str_contains($haystack, $needle)) {
				$partial = $task;
			}
		}

		$found = $exact ?? $partial;

		return $found !== null
			? McpTaskDto::fromEntity(
				$found,
				$this->taskFieldValueProvider->findByTask($found),
				$this->taskTagProvider->getTagIdsForTask($found),
			)
			: null;
	}

	/**
	 * Get a single task by numeric ID or by code (e.g. "MP-3").
	 *
	 * @param int|string $taskId Task ID or code
	 */
	#[McpTool(name: 'get_task', description: 'Get a single task by numeric ID or code (e.g. "MP-3")')]
	public function getTask(int|string $taskId): McpTaskDto
	{
		$task = $this->requireTask($taskId);
		return McpTaskDto::fromEntity(
			$task,
			$this->taskFieldValueProvider->findByTask($task),
			$this->taskTagProvider->getTagIdsForTask($task),
		);
	}

	/**
	 * Create a new task. By default it lands in the project's Start status (e.g. "To Do").
	 * Provide statusId or statusName to put it in a different column.
	 *
	 * @param int $projectId Project ID
	 * @param string $name Task name
	 * @param string|null $description Optional markdown description
	 * @param int|null $priorityId Priority ID from the workspace's catalog (preferred). When omitted, the workspace's default priority is used.
	 * @param string|null $priorityName Priority name lookup (case-insensitive). Accepts the legacy "Low"/"Medium"/"High" against seeded defaults.
	 * @param int|null $statusId Optional explicit status ID
	 * @param string|null $statusName Optional status name (case-insensitive); ignored if statusId is given
	 * @param string|null $dueDate Optional due date (YYYY-MM-DD)
	 * @param string|null $startDate Optional start date (YYYY-MM-DD). Must not be after dueDate. Used by the Timeline view.
	 * @param int|null $assigneeId Optional user ID to assign. Defaults to the current MCP user. Must be a member of the project's workspace.
	 * @param array<array{fieldId: int, value: ?string}>|null $fieldValues Optional custom-field values keyed by fieldId
	 * @param list<int>|null $tagIds Optional list of workspace tag IDs to apply to the new task
	 * @param array{cadence: string, interval?: int, weekday?: ?int, dayOfMonth?: ?int, cronExpression?: ?string, endType?: string, endDate?: ?string, maxOccurrences?: ?int, anchorDate?: ?string}|null $recurrence Optional recurrence rule. See set_task_recurrence for the field semantics.
	 */
	#[McpTool(
		name: 'create_task',
		description: 'Create a task in a project. Lands in Start status by default. Pass recurrence to make it repeat.',
	)]
	public function createTask(
		int $projectId,
		string $name,
		?string $description = null,
		?int $priorityId = null,
		?string $priorityName = null,
		?int $statusId = null,
		?string $statusName = null,
		?string $dueDate = null,
		?string $startDate = null,
		?int $assigneeId = null,
		?array $fieldValues = null,
		?array $tagIds = null,
		?array $recurrence = null,
	): McpTaskDto {
		$user = $this->userContext->getUser();
		$project = $this->requireProject($projectId);
		$priority = $this->priorityResolver->resolve($project, $priorityId, $priorityName);
		if ($priority === null) {
			throw new RuntimeException('Workspace has no priorities configured.');
		}
		$status = $this->statusResolver->resolve($project, $statusId, $statusName)
			?? $this->statusResolver->findByType($project, StatusTypeEnum::Start)
			?? throw new RuntimeException(sprintf('No Start status found for project %d.', $projectId));

		$assignee = $assigneeId !== null ? $this->resolveAssignee($project, $assigneeId) : $user;

		// Validate the recurrence payload up front so a bad rule fails before a task is created.
		$recurrenceConfig = $recurrence !== null ? TaskRecurrenceWriteDto::fromArray($recurrence)->toConfig() : null;

		$task = $this->taskProvider->createTask(
			author: $user,
			project: $project,
			status: $status,
			name: $name,
			description: $description,
			priority: $priority,
			dueDate: DateInput::parse($dueDate, 'dueDate'),
			assignee: $assignee,
			fieldValues: $this->normalizeFieldValues($fieldValues),
			tagIds: $tagIds,
			startDate: DateInput::parse($startDate, 'startDate'),
		);

		if ($recurrenceConfig !== null) {
			$this->recurrenceProvider->set($user, $task, $recurrenceConfig);
		}

		return McpTaskDto::fromEntity(
			$task,
			$this->taskFieldValueProvider->findByTask($task),
			$this->taskTagProvider->getTagIdsForTask($task),
		);
	}

	/**
	 * Update a task's editable fields. Omitted parameters are left unchanged.
	 *
	 * @param int|string $taskId Task ID or code (e.g. "MP-3")
	 * @param string|null $name New name
	 * @param string|null $description New description
	 * @param int|null $priorityId New priority ID from the workspace's catalog (preferred over priorityName).
	 * @param string|null $priorityName New priority name (case-insensitive). Accepts the legacy "Low"/"Medium"/"High" against seeded defaults.
	 * @param string|null $dueDate New due date (YYYY-MM-DD), or empty string to clear
	 * @param string|null $startDate New start date (YYYY-MM-DD), or empty string to clear. Must not be after dueDate.
	 * @param int|null $assigneeId New assignee user ID. Pass null to clear (unassign). Omit the parameter to leave unchanged. Must be a member of the project's workspace.
	 * @param bool $clearAssignee Pass true together with omitting assigneeId to explicitly unassign the task.
	 * @param array<array{fieldId: int, value: ?string}>|null $fieldValues Optional custom-field values to replace
	 * @param list<int>|null $tagIds Optional list of workspace tag IDs to apply (replaces the full set)
	 */
	#[McpTool(
		name: 'update_task',
		description: 'Update a task. Use move_task for status, assigneeId for (re)assignment, or clearAssignee:true to unassign.',
	)]
	public function updateTask(
		int|string $taskId,
		?string $name = null,
		?string $description = null,
		?int $priorityId = null,
		?string $priorityName = null,
		?string $dueDate = null,
		?string $startDate = null,
		?int $assigneeId = null,
		bool $clearAssignee = false,
		?array $fieldValues = null,
		?array $tagIds = null,
	): McpTaskDto {
		$user = $this->userContext->getUser();
		$task = $this->requireTask($taskId);

		$newDueDate = $this->resolveNewDate($task->dueDate, $dueDate);
		$newStartDate = $this->resolveNewDate($task->startDate, $startDate);
		$assignee = $this->resolveAssigneeForUpdate($task, $assigneeId, $clearAssignee);
		$priority = $priorityId !== null || $priorityName !== null
			? ($this->priorityResolver->resolve($task->project, $priorityId, $priorityName) ?? $task->priority)
			: $task->priority;

		$updated = $this->taskProvider->updateTask(
			author: $user,
			task: $task,
			name: $name ?? $task->name,
			description: $description ?? $task->description,
			priority: $priority,
			dueDate: $newDueDate,
			status: $task->status,
			assignee: $assignee,
			fieldValues: $this->normalizeFieldValues($fieldValues),
			tagIds: $tagIds,
			startDate: $newStartDate,
		);

		return McpTaskDto::fromEntity(
			$updated,
			$this->taskFieldValueProvider->findByTask($updated),
			$this->taskTagProvider->getTagIdsForTask($updated),
		);
	}

	/**
	 * Move a task to a different status (column). Provide either statusId or statusName.
	 * The task is appended to the end of the destination column.
	 *
	 * @param int|string $taskId Task ID or code (e.g. "MP-3")
	 * @param int|null $statusId Target status ID
	 * @param string|null $statusName Target status name (case-insensitive); ignored if statusId is given
	 */
	#[McpTool(name: 'move_task', description: 'Move a task to a different status. Appends to the end of the destination column.')]
	public function moveTask(int|string $taskId, ?int $statusId = null, ?string $statusName = null): McpTaskDto
	{
		$user = $this->userContext->getUser();
		$task = $this->requireTask($taskId);
		$status = $this->statusResolver->resolve($task->project, $statusId, $statusName);
		if ($status === null) {
			throw new RuntimeException('Provide statusId or statusName to move the task.');
		}

		$position = $this->nextPositionInStatus($status->id);
		$moved = $this->taskProvider->moveTask($user, $task, $status, $position);

		return McpTaskDto::fromEntity(
			$moved,
			$this->taskFieldValueProvider->findByTask($moved),
			$this->taskTagProvider->getTagIdsForTask($moved),
		);
	}

	/**
	 * Archive a task. Archived tasks are hidden from boards and from the default task lists, but
	 * remain editable and can be unarchived. Records a TaskArchived event.
	 *
	 * @param int|string $taskId Task ID or code (e.g. "MP-3")
	 */
	#[McpTool(name: 'archive_task', description: 'Archive a task (hides it from boards and default lists; reversible).')]
	public function archiveTask(int|string $taskId): McpTaskDto
	{
		$user = $this->userContext->getUser();
		$task = $this->requireTask($taskId);

		$archived = $this->taskProvider->archiveTask($user, $task);

		return McpTaskDto::fromEntity(
			$archived,
			$this->taskFieldValueProvider->findByTask($archived),
			$this->taskTagProvider->getTagIdsForTask($archived),
		);
	}

	/**
	 * Unarchive a previously archived task, restoring it to boards and default lists.
	 * Records a TaskUnarchived event.
	 *
	 * @param int|string $taskId Task ID or code (e.g. "MP-3")
	 */
	#[McpTool(name: 'unarchive_task', description: 'Unarchive a task, restoring it to boards and default lists.')]
	public function unarchiveTask(int|string $taskId): McpTaskDto
	{
		$user = $this->userContext->getUser();
		$task = $this->requireTask($taskId);

		$unarchived = $this->taskProvider->unarchiveTask($user, $task);

		return McpTaskDto::fromEntity(
			$unarchived,
			$this->taskFieldValueProvider->findByTask($unarchived),
			$this->taskTagProvider->getTagIdsForTask($unarchived),
		);
	}

	/**
	 * Duplicate a task within its project and status. Clones the description, priority, due date,
	 * assignee, custom-field values, and tags. Comments, files, events, and relations are not cloned.
	 *
	 * @param int|string $taskId Task ID or code (e.g. "MP-3")
	 * @param string|null $name Optional name for the copy. Defaults to the source name with a " (copy)" suffix.
	 */
	#[McpTool(
		name: 'duplicate_task',
		description: 'Duplicate a task (clones content, fields, and tags — not comments, files, or relations).',
	)]
	public function duplicateTask(int|string $taskId, ?string $name = null): McpTaskDto
	{
		$user = $this->userContext->getUser();
		$task = $this->requireTask($taskId);

		$duplicate = $this->taskProvider->duplicateTask($user, $task, $name);

		return McpTaskDto::fromEntity(
			$duplicate,
			$this->taskFieldValueProvider->findByTask($duplicate),
			$this->taskTagProvider->getTagIdsForTask($duplicate),
		);
	}

	/**
	 * Delete a task.
	 *
	 * @param int|string $taskId Task ID or code (e.g. "MP-3")
	 */
	#[McpTool(name: 'delete_task', description: 'Delete a task (irreversible)')]
	public function deleteTask(int|string $taskId): string
	{
		$user = $this->userContext->getUser();
		$task = $this->requireTask($taskId);

		$this->taskProvider->deleteTask($user, $task);

		return 'Task deleted.';
	}

	/**
	 * Apply one operation to many tasks in the current workspace in a single batch.
	 * Per-task failures (not found, out of workspace, status mismatch) are returned as `skipped` —
	 * the call succeeds even if some ids could not be processed. Up to 200 ids per call.
	 *
	 * Operations and required `payload`:
	 * - "move": `{statusId: int}` — moves each task to the given status, appended to end of column
	 * - "tag": `{tagIds: int[]}` — adds these tag ids to each task's existing tags
	 * - "untag": `{tagIds: int[]}` — removes these tag ids from each task's existing tags
	 * - "assign": `{assigneeId: int|null}` — sets assignee; null unassigns
	 * - "priority": `{priorityId: int}` — sets each task's priority
	 * - "delete": no payload — deletes each task
	 *
	 * @param list<int> $ids Task IDs (1-200). Order is preserved (matters for "move").
	 * @param string $op Operation name: move | tag | untag | assign | priority | delete
	 * @param array<string, mixed>|null $payload Per-op payload (see above).
	 * @return array{succeeded: list<int>, skipped: list<array{id: int, reason: string}>}
	 */
	#[McpTool(
		name: 'bulk_update_tasks',
		description: 'Apply one operation to many tasks (move|tag|untag|assign|priority|delete). Returns {succeeded, skipped}.',
	)]
	public function bulkUpdateTasks(array $ids, string $op, ?array $payload = null): array
	{
		$user = $this->userContext->getUser();
		$workspace = $this->requireWorkspace();

		$opEnum = BulkOpEnum::tryFrom($op);
		if ($opEnum === null) {
			throw new RuntimeException(sprintf('Unknown op "%s". Expected one of: move, tag, untag, assign, priority, delete.', $op));
		}

		return $this->bulkTaskProvider->execute($user, $workspace, $opEnum, $ids, $payload ?? []);
	}

	private function requireWorkspace(): Workspace
	{
		$workspace = $this->workspaceProvider->getCurrentWorkspace($this->userContext->getUser());
		if ($workspace === null) {
			throw new RuntimeException('No active workspace.');
		}

		return $workspace;
	}

	private function requireProject(int $projectId): Project
	{
		$workspace = $this->requireWorkspace();
		$project = $this->projectProvider->getProject($workspace, $projectId);
		if ($project === null) {
			throw new RuntimeException(sprintf('Project %d not found.', $projectId));
		}

		return $project;
	}

	private function requireTask(int|string $taskId): Task
	{
		$task = $this->taskCodeResolver->resolveForUser($this->userContext->getUser(), (string) $taskId);
		if ($task === null) {
			throw new RuntimeException(sprintf('Task "%s" not found.', (string) $taskId));
		}
		return $task;
	}

	private function resolveAssignee(Project $project, int $assigneeId): User
	{
		$assignee = $this->userRepository->findUserById($assigneeId);
		if ($assignee === null || !$this->workspaceProvider->isMember($assignee, $project->workspace)) {
			throw new RuntimeException(sprintf(
				'Assignee user %d must be a member of the project\'s workspace.',
				$assigneeId,
			));
		}

		return $assignee;
	}

	private function resolveAssigneeForUpdate(Task $task, ?int $assigneeId, bool $clearAssignee): ?User
	{
		if ($assigneeId !== null) {
			return $this->resolveAssignee($task->project, $assigneeId);
		}
		if ($clearAssignee) {
			return null;
		}
		return $task->assignee;
	}

	/** Partial-update date semantics: null leaves the value unchanged, '' clears it, otherwise parse. */
	private function resolveNewDate(?DateTimeImmutable $current, ?string $value): ?DateTimeImmutable
	{
		if ($value === null) {
			return $current;
		}
		return DateInput::parse($value, 'date');
	}

	private function nextPositionInStatus(int $statusId): int
	{
		$status = $this->statusProvider->getStatus($statusId);
		if ($status === null) {
			throw new RuntimeException(sprintf('Status %d not found.', $statusId));
		}

		$max = -1;
		foreach ($this->taskProvider->getTasksByProject($status->workflow->project) as $task) {
			if ($task->status->id === $statusId && $task->position > $max) {
				$max = $task->position;
			}
		}

		return $max + 1;
	}

	/**
	 * @param array<array{fieldId: int, value: ?string}>|null $raw
	 * @return array<int, ?string>|null
	 */
	private function normalizeFieldValues(?array $raw): ?array
	{
		return $raw === null ? null : array_column($raw, 'value', 'fieldId');
	}
}
