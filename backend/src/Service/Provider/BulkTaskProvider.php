<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use RuntimeException;
use Ukolio\Model\Entity\Enum\EventTypeEnum;
use Ukolio\Model\Entity\Priority;
use Ukolio\Model\Entity\Status;
use Ukolio\Model\Entity\Task;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Model\Repository\TaskRepository;
use Ukolio\Model\Repository\UserRepository;
use Ukolio\Service\Provider\Enum\BulkOpEnum;

final readonly class BulkTaskProvider implements BulkTaskProviderInterface
{
	public const int MAX_IDS = 200;

	public function __construct(
		private TaskRepository $taskRepository,
		private TaskProviderInterface $taskProvider,
		private TaskTagProviderInterface $taskTagProvider,
		private PriorityProviderInterface $priorityProvider,
		private StatusProviderInterface $statusProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private UserRepository $userRepository,
		private EventProviderInterface $eventProvider,
		private BulkPayloadParser $payloadParser,
	) {
	}

	/**
	 * @param list<int> $ids
	 * @param array<string, mixed> $payload
	 * @return array{succeeded: list<int>, skipped: list<array{id: int, reason: string}>}
	 */
	public function execute(User $actor, Workspace $workspace, BulkOpEnum $op, array $ids, array $payload): array
	{
		$ids = $this->normaliseIds($ids);
		$context = $this->resolvePayloadContext($workspace, $op, $payload);
		$tasksById = $this->loadTasksById($ids);

		$succeeded = [];
		$skipped = [];
		foreach ($ids as $id) {
			$outcome = $this->processOne($actor, $workspace, $op, $context, $tasksById[$id] ?? null, $id);
			if ($outcome === null) {
				$succeeded[] = $id;
			} else {
				$skipped[] = ['id' => $id, 'reason' => $outcome];
			}
		}

		$this->eventProvider->recordWorkspaceEvent(
			$actor,
			$workspace,
			EventTypeEnum::TasksBulkUpdated,
			[
				'op' => $op->value,
				'payload' => $this->payloadParser->sanitise($payload),
				'succeededIds' => $succeeded,
				'skipped' => $skipped,
			],
		);

		return ['succeeded' => $succeeded, 'skipped' => $skipped];
	}

	/**
	 * @param list<int> $ids
	 * @return list<int>
	 */
	private function normaliseIds(array $ids): array
	{
		$cleaned = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
		if ($cleaned === []) {
			throw new RuntimeException('No ids provided.');
		}
		if (count($cleaned) > self::MAX_IDS) {
			throw new RuntimeException(sprintf('Too many ids (max %d).', self::MAX_IDS));
		}
		return $cleaned;
	}

	/**
	 * @param list<int> $ids
	 * @return array<int, Task>
	 */
	private function loadTasksById(array $ids): array
	{
		$tasksById = [];
		foreach ($this->taskRepository->findByIds($ids) as $task) {
			$tasksById[$task->id] = $task;
		}
		return $tasksById;
	}

	/**
	 * @param array{status?: Status, tagIds?: list<int>, assignee?: ?User, priority?: Priority} $context
	 * @return non-empty-string|null null on success, reason string on skip
	 */
	private function processOne(User $actor, Workspace $workspace, BulkOpEnum $op, array $context, ?Task $task, int $id,): ?string
	{
		if ($task === null) {
			return 'not_found';
		}
		if ($task->project->workspace->id !== $workspace->id) {
			return 'out_of_workspace';
		}

		try {
			$this->applyOp($actor, $task, $op, $context);
			return null;
		} catch (RuntimeException $e) {
			$reason = $e->getMessage();
			return $reason === '' ? 'failed' : $reason;
		}
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array{status?: Status, tagIds?: list<int>, assignee?: ?User, priority?: Priority}
	 */
	private function resolvePayloadContext(Workspace $workspace, BulkOpEnum $op, array $payload): array
	{
		return match ($op) {
			BulkOpEnum::Move => ['status' => $this->requireStatus($workspace, $payload)],
			BulkOpEnum::Tag, BulkOpEnum::Untag => ['tagIds' => $this->requireTagIds($payload)],
			BulkOpEnum::Assign => ['assignee' => $this->resolveAssignee($workspace, $payload)],
			BulkOpEnum::Priority => ['priority' => $this->requirePriority($workspace, $payload)],
			BulkOpEnum::Delete => [],
		};
	}

	/** @param array{status?: Status, tagIds?: list<int>, assignee?: ?User, priority?: Priority} $context */
	private function applyOp(User $actor, Task $task, BulkOpEnum $op, array $context): void
	{
		match ($op) {
			BulkOpEnum::Move => $this->doMove($actor, $task, $context['status'] ?? null),
			BulkOpEnum::Tag => $this->doTagAdd($task, $context['tagIds'] ?? []),
			BulkOpEnum::Untag => $this->doTagRemove($task, $context['tagIds'] ?? []),
			BulkOpEnum::Assign => $this->doAssign($actor, $task, $context['assignee'] ?? null),
			BulkOpEnum::Priority => $this->doPriority($actor, $task, $context['priority'] ?? null),
			BulkOpEnum::Delete => $this->taskProvider->deleteTask($actor, $task, recordEvent: false),
		};
	}

	private function doMove(User $actor, Task $task, ?Status $status): void
	{
		if ($status === null) {
			throw new RuntimeException('Internal: status not resolved.');
		}
		if ($status->workflow->project->id !== $task->project->id) {
			throw new RuntimeException('status_not_in_project');
		}
		$this->taskProvider->moveTask($actor, $task, $status, $this->taskProvider->nextPosition($status), recordEvent: false);
	}

	/** @param list<int> $payloadTagIds */
	private function doTagAdd(Task $task, array $payloadTagIds): void
	{
		$existing = $this->taskTagProvider->getTagIdsForTask($task);
		$merged = array_values(array_unique(array_merge($existing, $payloadTagIds)));
		$this->taskTagProvider->setTagsForTask($task->project->workspace, $task, $merged);
	}

	/** @param list<int> $payloadTagIds */
	private function doTagRemove(Task $task, array $payloadTagIds): void
	{
		$existing = $this->taskTagProvider->getTagIdsForTask($task);
		$kept = array_values(array_diff($existing, $payloadTagIds));
		$this->taskTagProvider->setTagsForTask($task->project->workspace, $task, $kept);
	}

	private function doAssign(User $actor, Task $task, ?User $assignee): void
	{
		$this->taskProvider->updateTask(
			author: $actor,
			task: $task,
			name: $task->name,
			description: $task->description,
			priority: $task->priority,
			dueDate: $task->dueDate,
			status: $task->status,
			assignee: $assignee,
			recordEvent: false,
		);
	}

	private function doPriority(User $actor, Task $task, ?Priority $priority): void
	{
		if ($priority === null) {
			throw new RuntimeException('Internal: priority not resolved.');
		}
		$this->taskProvider->updateTask(
			author: $actor,
			task: $task,
			name: $task->name,
			description: $task->description,
			priority: $priority,
			dueDate: $task->dueDate,
			status: $task->status,
			assignee: $task->assignee,
			recordEvent: false,
		);
	}

	/** @param array<string, mixed> $payload */
	private function requireStatus(Workspace $workspace, array $payload): Status
	{
		$statusId = $this->payloadParser->intOrNull($payload, 'statusId');
		if ($statusId === null) {
			throw new RuntimeException('Payload missing statusId.');
		}
		$status = $this->statusProvider->getStatus($statusId);
		if ($status === null || $status->workflow->project->workspace->id !== $workspace->id) {
			throw new RuntimeException('Status not found in this workspace.');
		}
		return $status;
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return list<int>
	 */
	private function requireTagIds(array $payload): array
	{
		$tagIds = $this->payloadParser->intList($payload, 'tagIds');
		if ($tagIds === []) {
			throw new RuntimeException('Payload missing tagIds.');
		}
		return $tagIds;
	}

	/** @param array<string, mixed> $payload */
	private function resolveAssignee(Workspace $workspace, array $payload): ?User
	{
		$assigneeId = $this->payloadParser->intOrNull($payload, 'assigneeId');
		if ($assigneeId === null) {
			return null;
		}
		$assignee = $this->userRepository->findUserById($assigneeId);
		if ($assignee === null || !$this->workspaceProvider->isMember($assignee, $workspace)) {
			throw new RuntimeException('Assignee must be a member of the workspace.');
		}
		return $assignee;
	}

	/** @param array<string, mixed> $payload */
	private function requirePriority(Workspace $workspace, array $payload): Priority
	{
		$priorityId = $this->payloadParser->intOrNull($payload, 'priorityId');
		if ($priorityId === null) {
			throw new RuntimeException('Payload missing priorityId.');
		}
		$priority = $this->priorityProvider->getPriority($workspace, $priorityId);
		if ($priority === null) {
			throw new RuntimeException('Priority not found in this workspace.');
		}
		return $priority;
	}
}
