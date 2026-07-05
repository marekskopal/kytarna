<?php

declare(strict_types=1);

namespace Kytario\Service\Notification;

use Kytario\Dto\NotificationEmailQueueDto;
use Kytario\Model\Entity\Enum\ActorTypeEnum;
use Kytario\Model\Entity\Enum\EventTypeEnum;
use Kytario\Model\Entity\Enum\NotificationTypeEnum;
use Kytario\Model\Entity\Event;
use Kytario\Model\Entity\Task;
use Kytario\Model\Entity\User;
use Kytario\Model\Repository\TaskRepository;
use Kytario\Model\Repository\UserRepository;
use Kytario\Service\Provider\NotificationProviderInterface;
use Kytario\Service\Provider\TaskWatcherProviderInterface;
use Kytario\Service\Queue\Enum\QueueEnum;
use Kytario\Service\Queue\QueuePublisher;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Turns audit events into per-user notifications (U-83). Hangs off EventProvider::recordEvent.
 * Recipients are the task's watchers + assignee; the actor is never notified about their own
 * action. Watching is auto-started on assignment (Trello-style). To curb agent churn, TaskMoved
 * notifications are suppressed when the move was made by an agent.
 */
final readonly class NotificationDispatcher implements NotificationDispatcherInterface
{
	private const array RelevantTypes = [
		EventTypeEnum::TaskAssigned,
		EventTypeEnum::TaskMoved,
	];

	public function __construct(
		private NotificationProviderInterface $notificationProvider,
		private TaskWatcherProviderInterface $taskWatcherProvider,
		private TaskRepository $taskRepository,
		private UserRepository $userRepository,
		private QueuePublisher $queuePublisher,
		private LoggerInterface $logger,
	) {
	}

	public function onEvent(Event $event): void
	{
		if (!in_array($event->type, self::RelevantTypes, true) || $event->taskId === null) {
			return;
		}

		try {
			$task = $this->taskRepository->findById($event->taskId);
			if ($task === null) {
				return;
			}

			$actorId = $event->author?->id;
			$actorName = $event->author?->name;
			$metadata = $this->decodeMetadata($event->metadata);

			switch ($event->type) {
				case EventTypeEnum::TaskAssigned:
					$this->handleAssigned($task, $actorId, $actorName, $metadata);
					break;
				case EventTypeEnum::TaskMoved:
					// Agents churn statuses; only humans moving a task should ping watchers.
					if ($event->actorType !== ActorTypeEnum::Agent) {
						$this->handleMoved($task, $actorId, $actorName, $metadata);
					}
					break;
				default:
					break;
			}
		} catch (Throwable $e) {
			// Fan-out is best-effort; it must never break the mutation that recorded the event.
			$this->logger->error('Notification dispatch failed: ' . $e->getMessage(), ['exception' => $e]);
		}
	}

	public function dispatchDueReminder(Task $task, NotificationTypeEnum $type, User $recipient): void
	{
		$this->notify($recipient, $type, $task, null, null, ['dueDate' => $task->dueDate?->format('Y-m-d')]);
	}

	/** @param array<string, mixed> $metadata */
	private function handleAssigned(Task $task, ?int $actorId, ?string $actorName, array $metadata): void
	{
		$assigneeId = is_int($metadata['assigneeId'] ?? null) ? $metadata['assigneeId'] : null;
		if ($assigneeId === null || $assigneeId === $actorId) {
			return;
		}

		$assignee = $this->userRepository->findUserById($assigneeId);
		if ($assignee === null) {
			return;
		}

		$this->taskWatcherProvider->watch($task, $assignee);
		$this->notify($assignee, NotificationTypeEnum::TaskAssigned, $task, $actorId, $actorName, []);
	}

	/** @param array<string, mixed> $metadata */
	private function handleMoved(Task $task, ?int $actorId, ?string $actorName, array $metadata): void
	{
		$extra = ['statusName' => is_string($metadata['toStatusName'] ?? null) ? $metadata['toStatusName'] : null];

		foreach ($this->recipientIds($task) as $userId) {
			if ($userId === $actorId) {
				continue;
			}
			$user = $this->userRepository->findUserById($userId);
			if ($user === null) {
				continue;
			}
			$this->notify($user, NotificationTypeEnum::TaskMoved, $task, $actorId, $actorName, $extra);
		}
	}

	/**
	 * Write the notification row and (for directed types) enqueue an email.
	 *
	 * @param array<string, mixed> $extra
	 */
	private function notify(User $recipient, NotificationTypeEnum $type, Task $task, ?int $actorId, ?string $actorName, array $extra,): void
	{
		$workspaceId = $task->project->workspace->id;
		$projectId = $task->project->id;
		$taskCode = $task->project->prefix . '-' . $task->sequenceNumber;

		$data = array_merge(['taskCode' => $taskCode, 'taskName' => $task->name], array_filter(
			$extra,
			static fn (mixed $value): bool => $value !== null,
		));

		$this->notificationProvider->create($recipient, $workspaceId, $type, $task->id, $projectId, $actorId, $actorName, $data);

		if (!$type->isEmailable()) {
			return;
		}

		try {
			$this->queuePublisher->publishMessage(
				new NotificationEmailQueueDto(
					recipientEmail: $recipient->email,
					recipientName: $recipient->name,
					locale: $recipient->locale,
					type: $type,
					actorName: $actorName,
					taskCode: $taskCode,
					taskName: $task->name,
					projectId: $projectId,
					statusName: is_string($extra['statusName'] ?? null) ? $extra['statusName'] : null,
					dueDate: is_string($extra['dueDate'] ?? null) ? $extra['dueDate'] : null,
				),
				QueueEnum::Notification,
			);
		} catch (Throwable $e) {
			// The in-app notification is already persisted; a queue outage must not abort the fan-out.
			$this->logger->warning('Notification email enqueue failed: ' . $e->getMessage(), ['exception' => $e]);
		}
	}

	/** @return list<int> task watchers ∪ assignee */
	private function recipientIds(Task $task): array
	{
		$ids = $this->taskWatcherProvider->listWatcherUserIds($task);
		if ($task->assignee !== null) {
			$ids[] = $task->assignee->id;
		}
		return array_values(array_unique($ids));
	}

	/** @return array<string, mixed> */
	private function decodeMetadata(string $json): array
	{
		$decoded = json_decode($json, true);
		if (!is_array($decoded)) {
			return [];
		}

		$result = [];
		foreach ($decoded as $key => $value) {
			$result[(string) $key] = $value;
		}
		return $result;
	}
}
