<?php

declare(strict_types=1);

namespace Kytario\Service\Provider;

use DateTimeImmutable;
use RuntimeException;
use Kytario\Model\Entity\Enum\EventTypeEnum;
use Kytario\Model\Entity\Enum\RecurrenceCadenceEnum;
use Kytario\Model\Entity\Enum\RecurrenceEndTypeEnum;
use Kytario\Model\Entity\Enum\StatusTypeEnum;
use Kytario\Model\Entity\Project;
use Kytario\Model\Entity\Status;
use Kytario\Model\Entity\Task;
use Kytario\Model\Entity\TaskRecurrence;
use Kytario\Model\Entity\User;
use Kytario\Model\Repository\TaskRecurrenceRepository;
use Kytario\Service\Recurrence\RecurrenceConfig;
use Kytario\Service\Recurrence\RecurrenceScheduler;
use Kytario\Service\Script\Trigger\CronEvaluatorInterface;

final readonly class TaskRecurrenceProvider implements TaskRecurrenceProviderInterface
{
	// Upper bounds keep pathological values away from DateTime::modify() (date overflow)
	// and cap how far a single rule can schedule ahead.
	private const int MaxInterval = 1000;
	private const int MaxOccurrencesCap = 10000;

	public function __construct(
		private TaskRecurrenceRepository $taskRecurrenceRepository,
		private EventProviderInterface $eventProvider,
		private TaskProviderInterface $taskProvider,
		private TaskFieldValueProviderInterface $taskFieldValueProvider,
		private TaskTagProviderInterface $taskTagProvider,
		private TaskChecklistProviderInterface $taskChecklistProvider,
		private WorkflowProviderInterface $workflowProvider,
		private StatusProviderInterface $statusProvider,
		private RecurrenceScheduler $scheduler,
		private CronEvaluatorInterface $cronEvaluator,
	) {
	}

	public function findByTask(Task $task): ?TaskRecurrence
	{
		return $this->taskRecurrenceRepository->findByTask($task->id);
	}

	public function findById(int $id): ?TaskRecurrence
	{
		return $this->taskRecurrenceRepository->findById($id);
	}

	public function set(User $author, Task $task, RecurrenceConfig $config): TaskRecurrence
	{
		$this->validate($config);

		$now = new DateTimeImmutable();
		$anchor = ($config->anchorDate ?? $task->dueDate ?? $now)->setTime(0, 0);

		$recurrence = $this->taskRecurrenceRepository->findByTask($task->id);
		if ($recurrence === null) {
			$recurrence = new TaskRecurrence(
				task: $task,
				createdBy: $author,
				cadence: $config->cadence,
				interval: $config->interval,
				anchorDate: $anchor,
				endType: $config->endType,
				weekday: $config->weekday,
				dayOfMonth: $config->dayOfMonth,
				cronExpression: $config->cronExpression,
				endDate: $config->endDate,
				maxOccurrences: $config->maxOccurrences,
			);
			$recurrence->createdAt = $now;
		} else {
			$recurrence->task = $task;
			$recurrence->createdBy = $author;
			$recurrence->cadence = $config->cadence;
			$recurrence->interval = $config->interval;
			$recurrence->anchorDate = $anchor;
			$recurrence->endType = $config->endType;
			$recurrence->weekday = $config->weekday;
			$recurrence->dayOfMonth = $config->dayOfMonth;
			$recurrence->cronExpression = $config->cronExpression;
			$recurrence->endDate = $config->endDate;
			$recurrence->maxOccurrences = $config->maxOccurrences;
			$recurrence->occurrenceCount = 0;
			$recurrence->lastSpawnedAt = null;
		}

		$recurrence->active = true;
		$recurrence->nextRunAt = $this->scheduler->computeFirstRun($recurrence, $now);
		if ($recurrence->nextRunAt === null) {
			$recurrence->active = false;
		}
		$recurrence->updatedAt = $now;
		$this->taskRecurrenceRepository->persist($recurrence);

		$this->eventProvider->recordEvent(
			$author,
			$task->project,
			EventTypeEnum::TaskRecurrenceSet,
			['taskName' => $task->name, 'cadence' => $config->cadence->value, 'interval' => $config->interval],
			$task->id,
		);

		return $recurrence;
	}

	public function clear(User $author, Task $task): void
	{
		$recurrence = $this->taskRecurrenceRepository->findByTask($task->id);
		if ($recurrence === null) {
			return;
		}

		$this->taskRecurrenceRepository->delete($recurrence);

		$this->eventProvider->recordEvent(
			$author,
			$task->project,
			EventTypeEnum::TaskRecurrenceCleared,
			['taskName' => $task->name],
			$task->id,
		);
	}

	public function spawnNext(TaskRecurrence $recurrence): ?Task
	{
		if (!$recurrence->active || $recurrence->nextRunAt === null) {
			return null;
		}

		if ($this->countReached($recurrence->endType, $recurrence->maxOccurrences, $recurrence->occurrenceCount)) {
			$this->deactivate($recurrence);

			return null;
		}

		$carrier = $recurrence->task;
		$project = $carrier->project;
		$startStatus = $this->findStartStatus($project);
		if ($startStatus === null) {
			$this->deactivate($recurrence);

			return null;
		}

		$occurrenceDate = $recurrence->nextRunAt;
		[$dueDate, $startDate] = $this->shiftDates($carrier, $occurrenceDate);

		$newTask = $this->taskProvider->createTask(
			author: $recurrence->createdBy,
			project: $project,
			status: $startStatus,
			name: $carrier->name,
			description: $carrier->description,
			priority: $carrier->priority,
			dueDate: $dueDate,
			assignee: $carrier->assignee,
			fieldValues: $this->taskFieldValueProvider->findByTask($carrier),
			tagIds: $this->taskTagProvider->getTagIdsForTask($carrier),
			startDate: $startDate,
		);

		foreach ($this->taskChecklistProvider->findByTask($carrier) as $item) {
			$this->taskChecklistProvider->createItem($newTask, $item->text, $item->dueDate, $item->assignee);
		}

		// Re-point the series to the freshly spawned task (single-carrier invariant) and advance.
		$recurrence->task = $newTask;
		$recurrence->occurrenceCount++;
		$recurrence->lastSpawnedAt = new DateTimeImmutable();
		$next = $this->scheduler->computeNextRun($recurrence, $occurrenceDate);
		if ($next === null || $this->countReached($recurrence->endType, $recurrence->maxOccurrences, $recurrence->occurrenceCount)) {
			$recurrence->active = false;
			$recurrence->nextRunAt = null;
		} else {
			$recurrence->nextRunAt = $next;
		}
		$recurrence->updatedAt = new DateTimeImmutable();
		$this->taskRecurrenceRepository->persist($recurrence);

		$this->eventProvider->recordEvent(
			$recurrence->createdBy,
			$project,
			EventTypeEnum::TaskRecurrenceSpawned,
			['taskName' => $newTask->name, 'occurrenceCount' => $recurrence->occurrenceCount, 'recurrenceId' => $recurrence->id],
			$newTask->id,
		);

		return $newTask;
	}

	private function validate(RecurrenceConfig $config): void
	{
		if ($config->interval < 1 || $config->interval > self::MaxInterval) {
			throw new RuntimeException(sprintf('Recurrence interval must be between 1 and %d.', self::MaxInterval));
		}

		if ($config->cadence === RecurrenceCadenceEnum::Cron) {
			if (
				$config->cronExpression === null
				|| trim($config->cronExpression) === ''
				|| !$this->cronEvaluator->isValid($config->cronExpression)
			) {
				throw new RuntimeException('A valid cron expression is required for a custom recurrence.');
			}
		}

		if ($config->weekday !== null && ($config->weekday < 0 || $config->weekday > 6)) {
			throw new RuntimeException('Weekday must be between 0 (Sunday) and 6 (Saturday).');
		}

		if ($config->dayOfMonth !== null && ($config->dayOfMonth < 1 || $config->dayOfMonth > 31)) {
			throw new RuntimeException('Day of month must be between 1 and 31.');
		}

		if ($config->endType === RecurrenceEndTypeEnum::OnDate && $config->endDate === null) {
			throw new RuntimeException('An end date is required when the recurrence ends on a date.');
		}

		if ($config->endType === RecurrenceEndTypeEnum::AfterCount && ($config->maxOccurrences === null || $config->maxOccurrences < 1)) {
			throw new RuntimeException('A positive occurrence count is required when the recurrence ends after a number of occurrences.');
		}

		if ($config->maxOccurrences !== null && $config->maxOccurrences > self::MaxOccurrencesCap) {
			throw new RuntimeException(sprintf('Occurrence count must not exceed %d.', self::MaxOccurrencesCap));
		}
	}

	private function countReached(RecurrenceEndTypeEnum $endType, ?int $maxOccurrences, int $occurrenceCount): bool
	{
		return $endType === RecurrenceEndTypeEnum::AfterCount
			&& $maxOccurrences !== null
			&& (1 + $occurrenceCount) >= $maxOccurrences;
	}

	private function deactivate(TaskRecurrence $recurrence): void
	{
		$recurrence->active = false;
		$recurrence->nextRunAt = null;
		$recurrence->updatedAt = new DateTimeImmutable();
		$this->taskRecurrenceRepository->persist($recurrence);
	}

	/**
	 * The new occurrence's due date is the scheduled date; the start↔due span is preserved.
	 *
	 * @return array{0: ?DateTimeImmutable, 1: ?DateTimeImmutable} [dueDate, startDate]
	 */
	private function shiftDates(Task $carrier, DateTimeImmutable $occurrenceDate): array
	{
		$occurrence = $occurrenceDate->setTime(0, 0);

		if ($carrier->dueDate !== null) {
			if ($carrier->startDate !== null) {
				$spanDays = (int) $carrier->startDate->diff($carrier->dueDate)->format('%a');

				return [$occurrence, $occurrence->modify('-' . $spanDays . ' days')];
			}

			return [$occurrence, null];
		}

		if ($carrier->startDate !== null) {
			return [null, $occurrence];
		}

		return [null, null];
	}

	private function findStartStatus(Project $project): ?Status
	{
		$workflow = $this->workflowProvider->getWorkflowByProject($project);
		if ($workflow === null) {
			return null;
		}

		foreach ($this->statusProvider->getStatuses($workflow) as $status) {
			if ($status->type === StatusTypeEnum::Start) {
				return $status;
			}
		}

		return null;
	}
}
