<?php

declare(strict_types=1);

namespace Kytario\Command;

use DateTimeImmutable;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Kytario\App\ApplicationFactory;
use Kytario\Model\Entity\Enum\ScriptTriggerEnum;
use Kytario\Model\Repository\ScriptRepository;
use Kytario\Service\Cache\CacheFactory;
use Kytario\Service\Script\ScriptRunDispatcherInterface;
use Kytario\Service\Script\Trigger\CronEvaluatorInterface;
use const DATE_ATOM;

/**
 * Dispatches scheduled scripts whose cron expression is due. Intended to run once per minute from
 * system cron: `* * * * * php /app/bin/console scripts:tick`. Each due script is enqueued to the
 * script-run queue and executed by the v8js worker.
 */
final class ScriptScheduleTickCommand extends AbstractCommand
{
	protected function configure(): void
	{
		$this->setName('scripts:tick')
			->setDescription('Dispatch scheduled scripts that are due now (run every minute from cron).');
	}

	protected function process(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);
		$application = ApplicationFactory::create();

		$scriptRepository = $application->container->get(ScriptRepository::class);
		assert($scriptRepository instanceof ScriptRepository);
		$cronEvaluator = $application->container->get(CronEvaluatorInterface::class);
		assert($cronEvaluator instanceof CronEvaluatorInterface);
		$dispatcher = $application->container->get(ScriptRunDispatcherInterface::class);
		assert($dispatcher instanceof ScriptRunDispatcherInterface);

		// Per-(script, minute) guard so a script is dispatched at most once per minute even if the
		// cron fires scripts:tick more than once in the same minute. Best-effort (not atomic);
		// the once-a-minute cron makes overlap unlikely.
		$cache = CacheFactory::createPsrCache(namespace: 'ScriptTick');

		$now = new DateTimeImmutable();
		$minuteKey = $now->format('YmdHi');
		$dispatched = 0;

		foreach ($scriptRepository->findActiveByTrigger(ScriptTriggerEnum::Scheduled) as $script) {
			$expression = $script->triggerConfig;
			if ($expression === null || !$cronEvaluator->isDue($expression, $now)) {
				continue;
			}

			$lockKey = $script->id . ':' . $minuteKey;
			if ($cache->has($lockKey)) {
				continue;
			}
			$cache->set($lockKey, true, 120);

			$dispatcher->dispatch($script, ScriptTriggerEnum::Scheduled, null, $now->format(DATE_ATOM));
			$dispatched++;
		}

		$io->writeln(sprintf('Dispatched %d scheduled script(s) at %s.', $dispatched, $now->format(DATE_ATOM)));

		return self::SUCCESS;
	}
}
