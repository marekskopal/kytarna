<?php

declare(strict_types=1);

namespace Ukolio\Command;

use DateTimeImmutable;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Ukolio\App\ApplicationFactory;
use Ukolio\Dto\RecurringTaskSpawnQueueDto;
use Ukolio\Model\Repository\TaskRecurrenceRepository;
use Ukolio\Service\Cache\CacheFactory;
use Ukolio\Service\Queue\Enum\QueueEnum;
use Ukolio\Service\Queue\QueuePublisher;
use const DATE_ATOM;

/**
 * Safety-net half of the hybrid recurrence model (U-67): spawns date-anchored series whose next run
 * has passed even when the previous occurrence was never completed. Intended to run hourly from system
 * cron: `0 * * * * php /app/bin/console recurring-tasks:tick`. The spawn-on-complete event-trigger
 * handles the common case; this catches the rest. Dedup against that path lives in the spawn handler.
 */
final class RecurringTaskTickCommand extends AbstractCommand
{
	protected function configure(): void
	{
		$this->setName('recurring-tasks:tick')
			->setDescription('Spawn recurring tasks whose next run is due now (run hourly from cron).');
	}

	protected function process(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);
		$application = ApplicationFactory::create();

		$recurrenceRepository = $application->container->get(TaskRecurrenceRepository::class);
		assert($recurrenceRepository instanceof TaskRecurrenceRepository);
		$queuePublisher = $application->container->get(QueuePublisher::class);
		assert($queuePublisher instanceof QueuePublisher);

		// Per-(recurrence, day) guard so an in-flight spawn is not re-enqueued by the next hourly tick.
		$cache = CacheFactory::createPsrCache(namespace: 'RecurringTaskTick');

		$now = new DateTimeImmutable();
		$dayKey = $now->format('Ymd');
		$dispatched = 0;

		foreach ($recurrenceRepository->findDue($now) as $recurrence) {
			$lockKey = $recurrence->id . ':' . $dayKey;
			if ($cache->has($lockKey)) {
				continue;
			}
			$cache->set($lockKey, true, 3600);

			$queuePublisher->publishMessage(
				new RecurringTaskSpawnQueueDto($recurrence->id, $recurrence->task->id),
				QueueEnum::RecurringTaskSpawn,
			);
			$dispatched++;
		}

		$io->writeln(sprintf('Dispatched %d recurring task spawn(s) at %s.', $dispatched, $now->format(DATE_ATOM)));

		return self::SUCCESS;
	}
}
