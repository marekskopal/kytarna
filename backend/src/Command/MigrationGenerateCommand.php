<?php

declare(strict_types=1);

namespace Ukolio\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Ukolio\App\ApplicationFactory;

final class MigrationGenerateCommand extends AbstractCommand
{
	protected function configure(): void
	{
		$this->setName('migration:generate');
	}

	protected function process(InputInterface $input, OutputInterface $output): int
	{
		$application = ApplicationFactory::create();

		$application->dbContext->getMigrator()->generate(
			$application->dbContext->getSchema(),
			name: 'NewMigration',
		);

		return self::SUCCESS;
	}
}
