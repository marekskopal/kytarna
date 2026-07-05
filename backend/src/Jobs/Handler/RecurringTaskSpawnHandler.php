<?php

declare(strict_types=1);

namespace Ukolio\Jobs\Handler;

use Psr\Log\LoggerInterface;
use Ukolio\Dto\RecurringTaskSpawnQueueDto;
use Ukolio\Jobs\Message\ReceivedMessageInterface;
use Ukolio\Service\Provider\TaskRecurrenceProviderInterface;
use Ukolio\Service\Task\TaskServiceInterface;

final readonly class RecurringTaskSpawnHandler implements JobHandler
{
	public function __construct(
		private LoggerInterface $logger,
		private TaskServiceInterface $taskService,
		private TaskRecurrenceProviderInterface $taskRecurrenceProvider,
	) {
	}

	public function handle(ReceivedMessageInterface $message): void
	{
		$payload = $this->taskService->getPayloadDto($message, RecurringTaskSpawnQueueDto::class);

		$recurrence = $this->taskRecurrenceProvider->findById($payload->recurrenceId);
		if ($recurrence === null || !$recurrence->active) {
			return;
		}

		// Dedup: the carrier has already advanced, so another trigger spawned this occurrence first.
		if ($recurrence->task->id !== $payload->expectedCarrierTaskId) {
			return;
		}

		$spawned = $this->taskRecurrenceProvider->spawnNext($recurrence);
		if ($spawned === null) {
			$this->logger->info('Recurrence reached its end; no occurrence spawned', ['recurrenceId' => $payload->recurrenceId]);
		}
	}
}
