<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use RuntimeException;
use Ukolio\Dto\TaskRecurrenceDto;
use Ukolio\Dto\TaskRecurrenceWriteDto;
use Ukolio\Mcp\McpUserContextInterface;
use Ukolio\Model\Entity\Task;
use Ukolio\Service\Provider\TaskCodeResolverInterface;
use Ukolio\Service\Provider\TaskRecurrenceProviderInterface;

final readonly class TaskRecurrenceTools
{
	public function __construct(
		private McpUserContextInterface $userContext,
		private TaskCodeResolverInterface $taskCodeResolver,
		private TaskRecurrenceProviderInterface $recurrenceProvider,
	) {
	}

	/**
	 * Get the recurrence rule attached to a task, or null if it does not recur.
	 *
	 * @param int|string $taskId Task ID or code (e.g. "MP-3")
	 * @phpstan-impure Reads mutable persistence; set/clear between calls changes the result.
	 */
	#[McpTool(name: 'get_task_recurrence', description: 'Get a task\'s recurrence rule, or null if the task does not recur.')]
	public function getTaskRecurrence(int|string $taskId): ?TaskRecurrenceDto
	{
		$task = $this->requireTask($taskId);
		$recurrence = $this->recurrenceProvider->findByTask($task);

		return $recurrence === null ? null : TaskRecurrenceDto::fromEntity($recurrence);
	}

	/**
	 * Set (or replace) a task's recurrence rule. When the task is moved to a Finish status — or when the
	 * daily safety tick runs — the next occurrence is spawned as a fresh task carrying the same content,
	 * tags, custom fields, and checklist, with its due/start dates shifted to the next period.
	 *
	 * @param int|string $taskId Task ID or code (e.g. "MP-3")
	 * @param string $cadence One of "Daily", "Weekly", "Monthly", "Cron"
	 * @param int $interval Repeat every N units (e.g. 2 = every other week). Use 1 for "Cron". Default 1.
	 * @param int|null $weekday For "Weekly": 0 (Sunday) – 6 (Saturday). Defaults to the anchor date's weekday.
	 * @param int|null $dayOfMonth For "Monthly": 1–31 (clamped to month length). Defaults to the anchor date's day.
	 * @param string|null $cronExpression For "Cron": a 5-field cron expression (e.g. "0 9 * * 1").
	 * @param string $endType One of "Never", "OnDate", "AfterCount". Default "Never".
	 * @param string|null $endDate For "OnDate": stop after this date (YYYY-MM-DD).
	 * @param int|null $maxOccurrences For "AfterCount": total occurrences including the original task.
	 * @param string|null $anchorDate Phase reference for interval presets (YYYY-MM-DD). Defaults to the task's due date or today.
	 */
	#[McpTool(name: 'set_task_recurrence', description: 'Attach or replace a recurrence rule on a task (Daily/Weekly/Monthly/Cron).')]
	public function setTaskRecurrence(
		int|string $taskId,
		string $cadence,
		int $interval = 1,
		?int $weekday = null,
		?int $dayOfMonth = null,
		?string $cronExpression = null,
		string $endType = 'Never',
		?string $endDate = null,
		?int $maxOccurrences = null,
		?string $anchorDate = null,
	): TaskRecurrenceDto {
		$user = $this->userContext->getUser();
		$task = $this->requireTask($taskId);

		$config = TaskRecurrenceWriteDto::fromArray([
			'cadence' => $cadence,
			'interval' => $interval,
			'weekday' => $weekday,
			'dayOfMonth' => $dayOfMonth,
			'cronExpression' => $cronExpression,
			'endType' => $endType,
			'endDate' => $endDate,
			'maxOccurrences' => $maxOccurrences,
			'anchorDate' => $anchorDate,
		])->toConfig();

		$recurrence = $this->recurrenceProvider->set($user, $task, $config);

		return TaskRecurrenceDto::fromEntity($recurrence);
	}

	/**
	 * Remove a task's recurrence rule. The task itself is untouched; it simply stops spawning occurrences.
	 *
	 * @param int|string $taskId Task ID or code (e.g. "MP-3")
	 */
	#[McpTool(name: 'clear_task_recurrence', description: 'Remove a task\'s recurrence rule (the task itself is kept).')]
	public function clearTaskRecurrence(int|string $taskId): string
	{
		$user = $this->userContext->getUser();
		$task = $this->requireTask($taskId);

		$this->recurrenceProvider->clear($user, $task);

		return 'Recurrence cleared.';
	}

	private function requireTask(int|string $taskId): Task
	{
		$task = $this->taskCodeResolver->resolveForUser($this->userContext->getUser(), (string) $taskId);
		if ($task === null) {
			throw new RuntimeException(sprintf('Task "%s" not found.', (string) $taskId));
		}

		return $task;
	}
}
