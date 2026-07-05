<?php

declare(strict_types=1);

namespace Ukolio\Service\Recurrence;

use Psr\Log\LoggerInterface;
use Throwable;
use Ukolio\Dto\RecurringTaskSpawnQueueDto;
use Ukolio\Model\Entity\Enum\EventTypeEnum;
use Ukolio\Model\Entity\Enum\StatusTypeEnum;
use Ukolio\Model\Entity\Event;
use Ukolio\Model\Repository\StatusRepository;
use Ukolio\Model\Repository\TaskRecurrenceRepository;
use Ukolio\Service\Queue\Enum\QueueEnum;
use Ukolio\Service\Queue\QueuePublisher;

/**
 * Spawn-on-complete half of the hybrid recurrence model. Hangs off EventProvider::recordEvent the
 * same way the notification dispatcher and script event-trigger do: when a task carrying an active
 * recurrence is moved into a Finish status, the next occurrence is enqueued. The daily safety tick
 * (`recurring-tasks:tick`) covers date-anchored series the user never completed.
 */
final readonly class RecurrenceTrigger implements RecurrenceTriggerInterface
{
	public function __construct(
		private TaskRecurrenceRepository $taskRecurrenceRepository,
		private StatusRepository $statusRepository,
		private QueuePublisher $queuePublisher,
		private LoggerInterface $logger,
	) {
	}

	public function onEvent(Event $event): void
	{
		if ($event->type !== EventTypeEnum::TaskMoved || $event->taskId === null) {
			return;
		}

		try {
			$recurrence = $this->taskRecurrenceRepository->findByTask($event->taskId);
			if ($recurrence === null || !$recurrence->active) {
				return;
			}

			$toStatusId = $this->toStatusId($event->metadata);
			if ($toStatusId === null) {
				return;
			}

			$status = $this->statusRepository->findById($toStatusId);
			if ($status === null || $status->type !== StatusTypeEnum::Finish) {
				return;
			}

			$this->queuePublisher->publishMessage(
				new RecurringTaskSpawnQueueDto($recurrence->id, $event->taskId),
				QueueEnum::RecurringTaskSpawn,
			);
		} catch (Throwable $e) {
			// Best-effort: a queue or lookup failure must never break the move that recorded the event.
			$this->logger->error('Recurrence spawn-on-complete dispatch failed: ' . $e->getMessage(), ['exception' => $e]);
		}
	}

	private function toStatusId(string $metadataJson): ?int
	{
		$decoded = json_decode($metadataJson, true);
		if (!is_array($decoded)) {
			return null;
		}

		$value = $decoded['toStatusId'] ?? null;

		return is_int($value) ? $value : null;
	}
}
