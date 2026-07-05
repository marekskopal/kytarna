<?php

declare(strict_types=1);

namespace Ukolio\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Ukolio\App\ApplicationFactory;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Model\Repository\ProjectRepository;
use Ukolio\Model\Repository\TaskRepository;
use Ukolio\Model\Repository\WorkspaceRepository;
use Ukolio\Service\Search\MeiliClient;

final class SearchReindexCommand extends AbstractCommand
{
	protected function configure(): void
	{
		$this->setName('search:reindex')
			->setDescription('Rebuild the Meilisearch tasks index. Run once after deploy and any time the index settings change.')
			->addOption('workspace', null, InputOption::VALUE_REQUIRED, 'Restrict reindex to a single workspace ID')
			->addOption('flush', null, InputOption::VALUE_NONE, 'Drop all existing documents before reindexing');
	}

	protected function process(InputInterface $input, OutputInterface $output): int
	{
		$io = new SymfonyStyle($input, $output);
		$application = ApplicationFactory::create();

		$meiliClient = $application->container->get(MeiliClient::class);
		assert($meiliClient instanceof MeiliClient);
		$workspaceRepository = $application->container->get(WorkspaceRepository::class);
		assert($workspaceRepository instanceof WorkspaceRepository);
		$projectRepository = $application->container->get(ProjectRepository::class);
		assert($projectRepository instanceof ProjectRepository);
		$taskRepository = $application->container->get(TaskRepository::class);
		assert($taskRepository instanceof TaskRepository);

		$io->section('Ensuring Meilisearch index and settings…');
		$meiliClient->ensureIndex();

		if ($input->getOption('flush') === true) {
			$io->warning('Flushing all documents from index ' . $meiliClient->indexName());
			$meiliClient->deleteAllDocuments();
		}

		$workspaceFilter = $input->getOption('workspace');
		$workspaceId = is_string($workspaceFilter) && $workspaceFilter !== '' ? (int) $workspaceFilter : null;

		$totalIndexed = 0;
		$totalWorkspaces = 0;

		$workspaces = $workspaceId !== null
			? array_filter(
				[$workspaceRepository->findWorkspaceById($workspaceId)],
				static fn (?Workspace $w): bool => $w !== null,
			)
			: iterator_to_array($workspaceRepository->findAllWorkspaces(), false);

		foreach ($workspaces as $workspace) {
			$totalWorkspaces++;
			$workspaceCount = 0;
			foreach ($projectRepository->findProjectsByWorkspace($workspace->id) as $project) {
				foreach ($taskRepository->findByProject($project->id) as $task) {
					$meiliClient->indexTask($task->id);
					$workspaceCount++;
					$totalIndexed++;
				}
			}
			$io->writeln(sprintf('  workspace #%d "%s" — indexed %d task(s)', $workspace->id, $workspace->name, $workspaceCount));
		}

		$io->success(sprintf('Reindex complete: %d task(s) across %d workspace(s).', $totalIndexed, $totalWorkspaces));

		return self::SUCCESS;
	}
}
