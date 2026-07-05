<?php

declare(strict_types=1);

namespace Kytario\Service\Search;

use Psr\Log\LoggerInterface;
use Throwable;
use Kytario\Dto\SearchReindexQueueDto;
use Kytario\Service\Queue\Enum\QueueEnum;
use Kytario\Service\Queue\QueuePublisher;

/**
 * Publishes async Meilisearch reindex jobs. Failures are logged and swallowed so a flaky
 * search backend never breaks a primary mutation (the next reindex covers the drift).
 */
final readonly class SearchIndexer
{
	public function __construct(private QueuePublisher $queuePublisher, private LoggerInterface $logger,)
	{
	}

	public function queueUpsert(int $taskId): void
	{
		$this->publish(SearchReindexQueueDto::upsert($taskId));
	}

	public function queueDelete(int $taskId): void
	{
		$this->publish(SearchReindexQueueDto::delete($taskId));
	}

	/** @param iterable<int> $taskIds */
	public function queueUpsertMany(iterable $taskIds): void
	{
		foreach ($taskIds as $id) {
			$this->queueUpsert($id);
		}
	}

	private function publish(SearchReindexQueueDto $dto): void
	{
		try {
			$this->queuePublisher->publishMessage($dto, QueueEnum::SearchReindex);
		} catch (Throwable $e) {
			$this->logger->warning(sprintf(
				'Failed to enqueue search reindex (task %d, op %s): %s',
				$dto->taskId,
				$dto->op,
				$e->getMessage(),
			));
		}
	}
}
