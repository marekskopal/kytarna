<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use RuntimeException;
use Ukolio\Mcp\Dto\McpTagDto;
use Ukolio\Mcp\Dto\McpTagListDto;
use Ukolio\Mcp\Dto\McpTaskDto;
use Ukolio\Mcp\McpUserContextInterface;
use Ukolio\Model\Entity\Enum\EventTypeEnum;
use Ukolio\Model\Entity\Tag;
use Ukolio\Model\Entity\Task;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Service\Auth\PermissionCheckerInterface;
use Ukolio\Service\Provider\EventProviderInterface;
use Ukolio\Service\Provider\TagProviderInterface;
use Ukolio\Service\Provider\TaskFieldValueProviderInterface;
use Ukolio\Service\Provider\TaskProviderInterface;
use Ukolio\Service\Provider\TaskTagProviderInterface;
use Ukolio\Service\Provider\WorkspaceProviderInterface;

final readonly class TagTools
{
	public function __construct(
		private McpUserContextInterface $userContext,
		private TagProviderInterface $tagProvider,
		private TaskTagProviderInterface $taskTagProvider,
		private TaskProviderInterface $taskProvider,
		private TaskFieldValueProviderInterface $taskFieldValueProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private PermissionCheckerInterface $permissionChecker,
		private EventProviderInterface $eventProvider,
	) {
	}

	/** List all tags in the current workspace. */
	#[McpTool(name: 'list_workspace_tags', description: 'List tags defined in the current workspace')]
	public function listWorkspaceTags(): McpTagListDto
	{
		$workspace = $this->requireWorkspace();
		$tags = [];
		foreach ($this->tagProvider->getTags($workspace) as $tag) {
			$tags[] = McpTagDto::fromEntity($tag);
		}
		return new McpTagListDto($tags);
	}

	/**
	 * Find a tag by case-insensitive name match in the current workspace.
	 *
	 * @param string $name Tag name to look up
	 */
	#[McpTool(name: 'find_tag_by_name', description: 'Find a tag in the current workspace by name (case-insensitive)')]
	public function findTagByName(string $name): ?McpTagDto
	{
		$workspace = $this->requireWorkspace();
		$needle = mb_strtolower(trim($name));
		foreach ($this->tagProvider->getTags($workspace) as $tag) {
			if (mb_strtolower($tag->name) === $needle) {
				return McpTagDto::fromEntity($tag);
			}
		}
		return null;
	}

	/**
	 * Create a new tag in the current workspace.
	 *
	 * @param string $name Tag name (unique per workspace)
	 * @param string $color Hex color, e.g. "#3b82f6"
	 */
	#[McpTool(name: 'create_tag', description: 'Create a new tag in the current workspace')]
	public function createTag(string $name, string $color): McpTagDto
	{
		$workspace = $this->requireWorkspace();
		$this->requireManageTags($workspace);

		$tag = $this->tagProvider->createTag(
			author: $this->userContext->getUser(),
			workspace: $workspace,
			name: $name,
			color: $color,
		);

		return McpTagDto::fromEntity($tag);
	}

	/**
	 * Update an existing tag in the current workspace.
	 *
	 * @param int $tagId Tag ID
	 * @param string|null $name New name
	 * @param string|null $color New hex color
	 */
	#[McpTool(name: 'update_tag', description: 'Update a tag in the current workspace')]
	public function updateTag(int $tagId, ?string $name = null, ?string $color = null): McpTagDto
	{
		$workspace = $this->requireWorkspace();
		$this->requireManageTags($workspace);
		$tag = $this->requireTag($tagId);

		$updated = $this->tagProvider->updateTag(
			author: $this->userContext->getUser(),
			tag: $tag,
			name: $name ?? $tag->name,
			color: $color ?? $tag->color,
		);

		return McpTagDto::fromEntity($updated);
	}

	/**
	 * Delete a tag. Detaches it from every task that referenced it.
	 *
	 * @param int $tagId Tag ID
	 */
	#[McpTool(name: 'delete_tag', description: 'Delete a tag (detaches from all tasks)')]
	public function deleteTag(int $tagId): string
	{
		$workspace = $this->requireWorkspace();
		$this->requireManageTags($workspace);
		$tag = $this->requireTag($tagId);

		$this->tagProvider->deleteTag($this->userContext->getUser(), $tag);

		return 'Tag deleted.';
	}

	/**
	 * Replace the full set of tags applied to a task with the given IDs. Pass [] to clear.
	 *
	 * @param int $taskId Task ID
	 * @param list<int> $tagIds Tag IDs (must all belong to the same workspace as the task)
	 */
	#[McpTool(name: 'set_task_tags', description: 'Replace the set of tags applied to a task')]
	public function setTaskTags(int $taskId, array $tagIds): McpTaskDto
	{
		$task = $this->requireTask($taskId);
		$workspace = $task->project->workspace;

		$tagChanges = $this->taskTagProvider->setTagsForTask($workspace, $task, $tagIds);

		if ($tagChanges['added'] !== [] || $tagChanges['removed'] !== []) {
			$this->eventProvider->recordEvent(
				$this->userContext->getUser(),
				$task->project,
				EventTypeEnum::TaskTagsUpdated,
				['taskName' => $task->name, 'added' => $tagChanges['added'], 'removed' => $tagChanges['removed']],
				$task->id,
			);
		}

		return McpTaskDto::fromEntity(
			$task,
			$this->taskFieldValueProvider->findByTask($task),
			$this->taskTagProvider->getTagIdsForTask($task),
		);
	}

	private function requireWorkspace(): Workspace
	{
		$workspace = $this->workspaceProvider->getCurrentWorkspace($this->userContext->getUser());
		if ($workspace === null) {
			throw new RuntimeException('No active workspace.');
		}
		return $workspace;
	}

	private function requireTag(int $tagId): Tag
	{
		$workspace = $this->requireWorkspace();
		$tag = $this->tagProvider->getTag($workspace, $tagId);
		if ($tag === null) {
			throw new RuntimeException(sprintf('Tag %d not found.', $tagId));
		}
		return $tag;
	}

	private function requireTask(int $taskId): Task
	{
		$task = $this->taskProvider->getTask($taskId);
		if ($task === null || !$this->workspaceProvider->isMember($this->userContext->getUser(), $task->project->workspace)) {
			throw new RuntimeException(sprintf('Task %d not found.', $taskId));
		}
		return $task;
	}

	private function requireManageTags(Workspace $workspace): void
	{
		if (!$this->permissionChecker->canManageTags($this->userContext->getUser(), $workspace)) {
			throw new RuntimeException('You do not have permission to manage tags.');
		}
	}
}
