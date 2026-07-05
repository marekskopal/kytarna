<?php

declare(strict_types=1);

namespace Ukolio\Service\Script\Host;

use DateTimeImmutable;
use RuntimeException;
use Ukolio\Mcp\Tool\Helper\PriorityResolver;
use Ukolio\Mcp\Tool\Helper\StatusResolver;
use Ukolio\Model\Entity\Enum\EventTypeEnum;
use Ukolio\Model\Entity\Enum\StatusTypeEnum;
use Ukolio\Model\Entity\Project;
use Ukolio\Model\Entity\Task;
use Ukolio\Model\Repository\Enum\ArchivedFilterEnum;
use Ukolio\Model\Repository\Enum\OrderDirectionEnum;
use Ukolio\Model\Repository\Enum\TaskOrderByEnum;
use Ukolio\Service\Provider\EventProviderInterface;
use Ukolio\Service\Provider\ProjectProviderInterface;
use Ukolio\Service\Provider\TaskCodeResolverInterface;
use Ukolio\Service\Provider\TaskCommentProviderInterface;
use Ukolio\Service\Provider\TaskProviderInterface;
use Ukolio\Service\Provider\TaskTagProviderInterface;

/**
 * Exposed to JS as `ukolio.tasks`. Every call is authorised as the script's owner and scoped to
 * the run's workspace via the same providers/resolvers the MCP tools use. Counts against the
 * per-run task-API cap.
 */
final readonly class TasksApi
{
	private const int DefaultLimit = 50;
	private const int MaxLimit = 200;

	public function __construct(
		private ScriptRunContext $context,
		private TaskProviderInterface $taskProvider,
		private TaskCodeResolverInterface $taskCodeResolver,
		private ProjectProviderInterface $projectProvider,
		private PriorityResolver $priorityResolver,
		private StatusResolver $statusResolver,
		private TaskCommentProviderInterface $commentProvider,
		private TaskTagProviderInterface $taskTagProvider,
		private EventProviderInterface $eventProvider,
	) {
	}

	/** @return list<array<string, mixed>> */
	public function list(mixed $filters = null): array
	{
		$this->context->recordTaskApiCall();

		$f = JsValue::toAssoc($filters);
		$limit = min(max(JsValue::int($f['limit'] ?? null) ?? self::DefaultLimit, 1), self::MaxLimit);
		$offset = max(JsValue::int($f['offset'] ?? null) ?? 0, 0);
		$statusIds = array_key_exists('statusIds', $f) ? JsValue::intList($f['statusIds']) : null;
		$archived = (bool) ($f['includeArchived'] ?? false) ? ArchivedFilterEnum::All : ArchivedFilterEnum::Active;

		$out = [];
		$tasks = $this->taskProvider->getTasksInWorkspace(
			$this->context->workspace,
			$limit,
			$offset,
			TaskOrderByEnum::CreatedAt,
			OrderDirectionEnum::Desc,
			JsValue::string($f['search'] ?? null),
			$statusIds,
			(bool) ($f['onlyActive'] ?? false),
			archived: $archived,
		);
		foreach ($tasks as $task) {
			$out[] = HostSerializer::task($task);
		}

		return $out;
	}

	/** @return array<string, mixed>|null */
	public function get(int|string $id): ?array
	{
		$this->context->recordTaskApiCall();
		$task = $this->resolve($id);

		return $task === null ? null : HostSerializer::task($task);
	}

	/** @return array<string, mixed> */
	public function create(mixed $input): array
	{
		$this->context->recordTaskApiCall();
		$data = JsValue::toAssoc($input);

		$projectId = JsValue::int($data['projectId'] ?? null) ?? throw new RuntimeException('tasks.create requires a projectId.');
		$name = JsValue::string($data['name'] ?? null) ?? throw new RuntimeException('tasks.create requires a name.');
		$project = $this->requireProject($projectId);

		$priority = $this->priorityResolver->resolve(
			$project,
			JsValue::int($data['priorityId'] ?? null),
			JsValue::string($data['priorityName'] ?? null),
		)
			?? throw new RuntimeException('Workspace has no priorities configured.');

		$status = $this->statusResolver->resolve(
			$project,
			JsValue::int($data['statusId'] ?? null),
			JsValue::string($data['statusName'] ?? null),
		)
			?? $this->statusResolver->findByType($project, StatusTypeEnum::Start)
			?? throw new RuntimeException(sprintf('No Start status found for project %d.', $projectId));

		$dueDate = JsValue::string($data['dueDate'] ?? null);

		$task = $this->taskProvider->createTask(
			author: $this->context->owner,
			project: $project,
			status: $status,
			name: $name,
			description: JsValue::string($data['description'] ?? null),
			priority: $priority,
			dueDate: $dueDate !== null && $dueDate !== '' ? new DateTimeImmutable($dueDate) : null,
			assignee: $this->context->owner,
		);

		return HostSerializer::task($task);
	}

	/** @return array<string, mixed> */
	public function move(int|string $id, string $statusName): array
	{
		$this->context->recordTaskApiCall();
		$task = $this->resolve($id) ?? throw new RuntimeException(sprintf('Task "%s" not found.', (string) $id));

		$status = $this->statusResolver->resolve($task->project, null, $statusName)
			?? throw new RuntimeException(sprintf('Status "%s" not found.', $statusName));

		$moved = $this->taskProvider->moveTask($this->context->owner, $task, $status, $this->taskProvider->nextPosition($status));

		return HostSerializer::task($moved);
	}

	/** @return array<string, mixed> */
	public function update(int|string $id, mixed $input): array
	{
		$this->context->recordTaskApiCall();
		$task = $this->resolve($id) ?? throw new RuntimeException(sprintf('Task "%s" not found.', (string) $id));
		$data = JsValue::toAssoc($input);
		$project = $task->project;

		$name = JsValue::string($data['name'] ?? null) ?? $task->name;
		$description = array_key_exists('description', $data) ? JsValue::string($data['description']) : $task->description;
		$priority = $this->priorityResolver->resolve(
			$project,
			JsValue::int($data['priorityId'] ?? null),
			JsValue::string($data['priorityName'] ?? null),
		) ?? $task->priority;
		$status = $this->statusResolver->resolve(
			$project,
			JsValue::int($data['statusId'] ?? null),
			JsValue::string($data['statusName'] ?? null),
		) ?? $task->status;

		$dueDate = $task->dueDate;
		if (array_key_exists('dueDate', $data)) {
			$raw = JsValue::string($data['dueDate']);
			$dueDate = $raw !== null && $raw !== '' ? new DateTimeImmutable($raw) : null;
		}

		$updated = $this->taskProvider->updateTask(
			author: $this->context->owner,
			task: $task,
			name: $name,
			description: $description,
			priority: $priority,
			dueDate: $dueDate,
			status: $status,
			assignee: $task->assignee,
		);

		return HostSerializer::task($updated);
	}

	/** @return array{id: int, deleted: true} */
	public function delete(int|string $id): array
	{
		$this->context->recordTaskApiCall();
		$task = $this->resolve($id) ?? throw new RuntimeException(sprintf('Task "%s" not found.', (string) $id));
		$taskId = $task->id;

		$this->taskProvider->deleteTask($this->context->owner, $task);

		return ['id' => $taskId, 'deleted' => true];
	}

	/** @return array<string, mixed> */
	public function setTags(int|string $id, mixed $tagIds): array
	{
		$this->context->recordTaskApiCall();
		$task = $this->resolve($id) ?? throw new RuntimeException(sprintf('Task "%s" not found.', (string) $id));
		$ids = JsValue::intList($tagIds);

		$changes = $this->taskTagProvider->setTagsForTask($this->context->workspace, $task, $ids);
		if ($changes['added'] !== [] || $changes['removed'] !== []) {
			$this->eventProvider->recordEvent(
				$this->context->owner,
				$task->project,
				EventTypeEnum::TaskTagsUpdated,
				['taskName' => $task->name, 'added' => $changes['added'], 'removed' => $changes['removed']],
				$task->id,
			);
		}

		return [...HostSerializer::task($task), 'tagIds' => $this->taskTagProvider->getTagIdsForTask($task)];
	}

	/** @return array<string, mixed> */
	public function archive(int|string $id): array
	{
		$this->context->recordTaskApiCall();
		$task = $this->resolve($id) ?? throw new RuntimeException(sprintf('Task "%s" not found.', (string) $id));

		$archived = $this->taskProvider->archiveTask($this->context->owner, $task);

		return HostSerializer::task($archived);
	}

	/** @return array<string, mixed> */
	public function unarchive(int|string $id): array
	{
		$this->context->recordTaskApiCall();
		$task = $this->resolve($id) ?? throw new RuntimeException(sprintf('Task "%s" not found.', (string) $id));

		$unarchived = $this->taskProvider->unarchiveTask($this->context->owner, $task);

		return HostSerializer::task($unarchived);
	}

	/** @return array{id: int, body: string} */
	public function addComment(int|string $id, string $body): array
	{
		$this->context->recordTaskApiCall();
		$task = $this->resolve($id) ?? throw new RuntimeException(sprintf('Task "%s" not found.', (string) $id));

		$comment = $this->commentProvider->createComment($this->context->owner, $task, $body);

		return ['id' => $comment->id, 'body' => $comment->body];
	}

	private function resolve(int|string $id): ?Task
	{
		// Strictly scope to the script's own workspace, not the owner's memberships — otherwise a
		// script in workspace A could act on a task in workspace B that its author also belongs to.
		return $this->taskCodeResolver->resolve($this->context->workspace, (string) $id);
	}

	private function requireProject(int $projectId): Project
	{
		return $this->projectProvider->getProject($this->context->workspace, $projectId)
			?? throw new RuntimeException(sprintf('Project %d not found.', $projectId));
	}
}
