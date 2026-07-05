<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use RuntimeException;
use Ukolio\Mcp\Dto\McpProjectDto;
use Ukolio\Mcp\Dto\McpProjectListDto;
use Ukolio\Mcp\McpUserContextInterface;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Service\Provider\ProjectProviderInterface;
use Ukolio\Service\Provider\WorkspaceProviderInterface;

final readonly class ProjectTools
{
	public function __construct(
		private McpUserContextInterface $userContext,
		private ProjectProviderInterface $projectProvider,
		private WorkspaceProviderInterface $workspaceProvider,
	) {
	}

	/** List all projects belonging to the user's current workspace. */
	#[McpTool(name: 'list_projects', description: 'List all projects in the current workspace')]
	public function listProjects(): McpProjectListDto
	{
		$workspace = $this->requireWorkspace();

		$projects = [];
		foreach ($this->projectProvider->getProjects($workspace) as $project) {
			$projects[] = McpProjectDto::fromEntity($project);
		}

		return new McpProjectListDto($projects);
	}

	/**
	 * Find a project by case-insensitive name match. Returns null if not found.
	 * Use this before creating a project to avoid duplicates.
	 *
	 * @param string $name Project name to search for (case-insensitive, exact match)
	 */
	#[McpTool(
		name: 'find_project_by_name',
		description: 'Find a project in the current workspace by name (case-insensitive, exact match). Returns null if not found.',
	)]
	public function findProjectByName(string $name): ?McpProjectDto
	{
		$workspace = $this->requireWorkspace();
		$needle = mb_strtolower($name);
		foreach ($this->projectProvider->getProjects($workspace) as $project) {
			if (mb_strtolower($project->name) === $needle) {
				return McpProjectDto::fromEntity($project);
			}
		}

		return null;
	}

	/**
	 * Get a single project by ID.
	 *
	 * @param int $projectId Project ID
	 */
	#[McpTool(name: 'get_project', description: 'Get a single project by ID')]
	public function getProject(int $projectId): McpProjectDto
	{
		$workspace = $this->requireWorkspace();
		$project = $this->projectProvider->getProject($workspace, $projectId);
		if ($project === null) {
			throw new RuntimeException(sprintf('Project %d not found.', $projectId));
		}

		return McpProjectDto::fromEntity($project);
	}

	/**
	 * Create a new project in the current workspace. A default workflow with statuses "To Do", "In Progress", "Done"
	 * is automatically created. Call find_project_by_name first to avoid duplicates.
	 *
	 * @param string $name Project name
	 * @param string|null $description Optional project description
	 */
	#[McpTool(
		name: 'create_project',
		description: 'Create a new project in the current workspace with the default To Do / In Progress / Done workflow',
	)]
	public function createProject(string $name, ?string $description = null): McpProjectDto
	{
		$workspace = $this->requireWorkspace();
		$project = $this->projectProvider->createProject($this->userContext->getUser(), $workspace, $name, $description);

		return McpProjectDto::fromEntity($project);
	}

	/**
	 * Delete a project and all its tasks and workflow data.
	 *
	 * @param int $projectId Project ID
	 */
	#[McpTool(name: 'delete_project', description: 'Delete a project (irreversible — removes all its tasks)')]
	public function deleteProject(int $projectId): string
	{
		$workspace = $this->requireWorkspace();
		$project = $this->projectProvider->getProject($workspace, $projectId);
		if ($project === null) {
			throw new RuntimeException(sprintf('Project %d not found.', $projectId));
		}

		$this->projectProvider->deleteProject($project);

		return 'Project deleted.';
	}

	private function requireWorkspace(): Workspace
	{
		$workspace = $this->workspaceProvider->getCurrentWorkspace($this->userContext->getUser());
		if ($workspace === null) {
			throw new RuntimeException('No active workspace. Create one in the Ukolio app first.');
		}

		return $workspace;
	}
}
