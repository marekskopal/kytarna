<?php

declare(strict_types=1);

namespace Ukolio\Command;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Ukolio\App\ApplicationFactory;

final class MigrationRunCommand extends AbstractCommand
{
	protected function configure(): void
	{
		$this->setName('migration:run');
	}

	protected function process(InputInterface $input, OutputInterface $output): int
	{
		$application = ApplicationFactory::create();

		$logger = $application->container->get(LoggerInterface::class);
		assert($logger instanceof LoggerInterface);

		try {
			$application->dbContext->getMigrator()->migrate();
		} catch (\Throwable $e) {
			$output->writeln($e->getMessage());
			$logger->error($e->getMessage(), ['exception' => $e]);
			return self::FAILURE;
		}

		$output->writeln('Migrations applied.');

		return self::SUCCESS;
	}
}
