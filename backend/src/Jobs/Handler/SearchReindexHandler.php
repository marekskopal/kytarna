<?php

declare(strict_types=1);

namespace Kytario\Jobs\Handler;

use Psr\Log\LoggerInterface;
use Kytario\Dto\SearchReindexQueueDto;
use Kytario\Jobs\Message\ReceivedMessageInterface;
use Kytario\Service\Search\MeiliClient;
use Kytario\Service\Task\TaskServiceInterface;

final readonly class SearchReindexHandler implements JobHandler
{
	public function __construct(
		private LoggerInterface $logger,
		private TaskServiceInterface $taskService,
		private MeiliClient $meiliClient,
	) {
	}

	public function handle(ReceivedMessageInterface $message): void
	{
		$payload = $this->taskService->getPayloadDto($message, SearchReindexQueueDto::class);

		if ($payload->op === SearchReindexQueueDto::OpDelete) {
			$this->meiliClient->deleteTask($payload->taskId);
			$this->logger->debug('Reindex (delete) task ' . $payload->taskId);
			return;
		}

		$this->meiliClient->indexTask($payload->taskId);
		$this->logger->debug('Reindex (upsert) task ' . $payload->taskId);
	}
}
