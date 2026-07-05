<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use RuntimeException;
use Ukolio\Mcp\Dto\McpPriorityDto;
use Ukolio\Mcp\Dto\McpPriorityListDto;
use Ukolio\Mcp\McpUserContextInterface;
use Ukolio\Model\Entity\Priority;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Service\Auth\PermissionCheckerInterface;
use Ukolio\Service\Provider\PriorityProviderInterface;
use Ukolio\Service\Provider\WorkspaceProviderInterface;

final readonly class PriorityTools
{
	public function __construct(
		private McpUserContextInterface $userContext,
		private PriorityProviderInterface $priorityProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private PermissionCheckerInterface $permissionChecker,
	) {
	}

	/** List all priorities defined in the current workspace, ordered by position. */
	#[McpTool(name: 'list_workspace_priorities', description: 'List priorities defined in the current workspace')]
	public function listWorkspacePriorities(): McpPriorityListDto
	{
		$workspace = $this->requireWorkspace();
		$priorities = [];
		foreach ($this->priorityProvider->getPriorities($workspace) as $priority) {
			$priorities[] = McpPriorityDto::fromEntity($priority);
		}
		return new McpPriorityListDto($priorities);
	}

	/**
	 * Find a priority by case-insensitive name match in the current workspace.
	 *
	 * @param string $name Priority name to look up
	 */
	#[McpTool(name: 'find_priority_by_name', description: 'Find a priority in the current workspace by name (case-insensitive)')]
	public function findPriorityByName(string $name): ?McpPriorityDto
	{
		$workspace = $this->requireWorkspace();
		$priority = $this->priorityProvider->findPriorityByName($workspace, $name);
		return $priority !== null ? McpPriorityDto::fromEntity($priority) : null;
	}

	/**
	 * Create a new priority in the current workspace.
	 *
	 * @param string $name Priority name (unique per workspace)
	 * @param string $color Hex color, e.g. "#3b82f6"
	 * @param int|null $position Optional 0-based position in the catalog. Defaults to end.
	 * @param bool $isDefault If true, becomes the workspace default (clears the flag on the previous default).
	 */
	#[McpTool(name: 'create_priority', description: 'Create a new priority in the current workspace')]
	public function createPriority(string $name, string $color, ?int $position = null, bool $isDefault = false): McpPriorityDto
	{
		$workspace = $this->requireWorkspace();
		$this->requireManagePriorities($workspace);

		$priority = $this->priorityProvider->createPriority($workspace, $name, $color, $isDefault);

		if ($position !== null && $position !== $priority->position) {
			$priority = $this->priorityProvider->movePriority($priority, $position);
		}

		return McpPriorityDto::fromEntity($priority);
	}

	/**
	 * Update an existing priority. Omitted fields are left unchanged.
	 *
	 * @param int $priorityId Priority ID
	 * @param string|null $name New name
	 * @param string|null $color New hex color
	 * @param bool|null $isDefault Pass true to make this the workspace default; false to clear the flag; null to leave unchanged.
	 */
	#[McpTool(name: 'update_priority', description: 'Update a priority in the current workspace')]
	public function updatePriority(int $priorityId, ?string $name = null, ?string $color = null, ?bool $isDefault = null): McpPriorityDto
	{
		$workspace = $this->requireWorkspace();
		$this->requireManagePriorities($workspace);
		$priority = $this->requirePriority($priorityId);

		$updated = $this->priorityProvider->updatePriority(
			priority: $priority,
			name: $name ?? $priority->name,
			color: $color ?? $priority->color,
			isDefault: $isDefault ?? $priority->isDefault,
		);

		return McpPriorityDto::fromEntity($updated);
	}

	/**
	 * Delete a priority. Fails when one or more tasks still reference it; reassign those tasks first.
	 *
	 * @param int $priorityId Priority ID
	 */
	#[McpTool(name: 'delete_priority', description: 'Delete a priority (blocked when tasks reference it)')]
	public function deletePriority(int $priorityId): string
	{
		$workspace = $this->requireWorkspace();
		$this->requireManagePriorities($workspace);
		$priority = $this->requirePriority($priorityId);

		$this->priorityProvider->deletePriority($priority);

		return 'Priority deleted.';
	}

	private function requireWorkspace(): Workspace
	{
		$workspace = $this->workspaceProvider->getCurrentWorkspace($this->userContext->getUser());
		if ($workspace === null) {
			throw new RuntimeException('No active workspace.');
		}
		return $workspace;
	}

	private function requirePriority(int $priorityId): Priority
	{
		$workspace = $this->requireWorkspace();
		$priority = $this->priorityProvider->getPriority($workspace, $priorityId);
		if ($priority === null) {
			throw new RuntimeException(sprintf('Priority %d not found.', $priorityId));
		}
		return $priority;
	}

	private function requireManagePriorities(Workspace $workspace): void
	{
		if (!$this->permissionChecker->canManagePriorities($this->userContext->getUser(), $workspace)) {
			throw new RuntimeException('You do not have permission to manage priorities.');
		}
	}
}
