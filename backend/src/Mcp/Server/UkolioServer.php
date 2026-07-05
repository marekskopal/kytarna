<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Server;

use Mcp\Server;
use Mcp\Server\Session\SessionStoreInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

final readonly class UkolioServer
{
	public function __construct(private ContainerInterface $container, private LoggerInterface $logger)
	{
	}

	public function build(?SessionStoreInterface $sessionStore = null): Server
	{
		$builder = Server::builder()
			->setContainer($this->container)
			->setLogger($this->logger)
			->setDiscovery(
				basePath: dirname(__DIR__, 2),
				scanDirs: ['Mcp/Tool'],
			)
			->setServerInfo(name: 'ukolio', version: '1.0.0', description: 'Ukolio MCP server — Kanban projects and tasks')
			->setInstructions(
				'This server manages Kanban projects and tasks for the authenticated user. '
				. 'Typical flow when creating tasks from an external source: '
				. '1) call find_project_by_name to check if the target project exists; '
				. '2) if it does not, call create_project (a default "To Do → In Progress → Done" workflow is created automatically); '
				. '3) call create_task for each item (defaults to the Start status, e.g. "To Do"). '
				. 'Typical flow when working on a task: '
				. '1) call find_task_by_name to locate the task; '
				. '2) call move_task with statusName="In Progress" to start it; '
				. '3) when finished, call move_task with statusName="Done". '
				. 'Use list_statuses to discover the column names for a given project — workflows are per-project and customizable.',
			);

		if ($sessionStore !== null) {
			$builder->setSession($sessionStore);
		}

		return $builder->build();
	}
}
