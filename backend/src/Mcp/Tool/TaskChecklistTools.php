<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Tool;

use DateTimeImmutable;
use Mcp\Capability\Attribute\McpTool;
use RuntimeException;
use Ukolio\Mcp\Dto\McpChecklistItemDto;
use Ukolio\Mcp\Dto\McpChecklistListDto;
use Ukolio\Mcp\McpUserContextInterface;
use Ukolio\Model\Entity\Task;
use Ukolio\Model\Entity\TaskChecklistItem;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Repository\UserRepository;
use Ukolio\Service\Provider\TaskChecklistProviderInterface;
use Ukolio\Service\Provider\TaskProviderInterface;
use Ukolio\Service\Provider\WorkspaceProviderInterface;

final readonly class TaskChecklistTools
{
	public function __construct(
		private McpUserContextInterface $userContext,
		private TaskProviderInterface $taskProvider,
		private TaskChecklistProviderInterface $checklistProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private UserRepository $userRepository,
	) {
	}

	/**
	 * List the checklist items of a task in order, with overall progress.
	 *
	 * @param int $taskId Task ID
	 */
	#[McpTool(name: 'list_task_checklist', description: 'List a task\'s checklist items (ordered) with progress.')]
	public function listTaskChecklist(int $taskId): McpChecklistListDto
	{
		$task = $this->requireTask($taskId);
		$items = $this->checklistProvider->findByTask($task);

		$done = 0;
		$dtos = [];
		foreach ($items as $item) {
			if ($item->checkedAt !== null) {
				$done++;
			}
			$dtos[] = McpChecklistItemDto::fromEntity($item);
		}

		return new McpChecklistListDto(items: $dtos, total: count($dtos), done: $done);
	}

	/**
	 * Add a checklist item to a task. New items are appended to the end of the list.
	 *
	 * @param int $taskId Task ID
	 * @param string $text Item text (non-empty, max 500 characters)
	 * @param string|null $dueDate Optional per-item due date (YYYY-MM-DD)
	 * @param int|null $assigneeId Optional per-item assignee user ID (must be a workspace member)
	 */
	#[McpTool(name: 'add_checklist_item', description: 'Append a checklist item to a task.')]
	public function addChecklistItem(int $taskId, string $text, ?string $dueDate = null, ?int $assigneeId = null): McpChecklistItemDto
	{
		$task = $this->requireTask($taskId);
		$assignee = $assigneeId !== null ? $this->requireMember($task, $assigneeId) : null;
		$dueDateValue = $dueDate !== null && $dueDate !== '' ? new DateTimeImmutable($dueDate) : null;

		$item = $this->checklistProvider->createItem($task, $text, $dueDateValue, $assignee);

		return McpChecklistItemDto::fromEntity($item);
	}

	/**
	 * Update a checklist item. Only the fields you pass are changed. To clear the due date pass
	 * an empty string; to unassign pass clearAssignee=true.
	 *
	 * @param int $itemId Checklist item ID
	 * @param string|null $text New text (non-empty, max 500 characters)
	 * @param string|null $dueDate New due date (YYYY-MM-DD), or empty string to clear; omit to leave unchanged
	 * @param int|null $assigneeId New assignee user ID; omit to leave unchanged
	 * @param bool $clearAssignee Pass true to unassign the item
	 * @param bool|null $checked Set the checked state; omit to leave unchanged
	 */
	#[McpTool(name: 'update_checklist_item', description: 'Update a checklist item\'s text, due date, assignee or checked state.')]
	public function updateChecklistItem(
		int $itemId,
		?string $text = null,
		?string $dueDate = null,
		?int $assigneeId = null,
		bool $clearAssignee = false,
		?bool $checked = null,
	): McpChecklistItemDto {
		$user = $this->userContext->getUser();
		$item = $this->requireItem($itemId);

		$assigneeProvided = $assigneeId !== null || $clearAssignee;
		$assignee = $assigneeId !== null ? $this->requireMember($item->task, $assigneeId) : null;

		$dueDateProvided = $dueDate !== null;
		$dueDateValue = $dueDate !== null && $dueDate !== '' ? new DateTimeImmutable($dueDate) : null;

		$updated = $this->checklistProvider->updateItem(
			item: $item,
			actor: $user,
			text: $text,
			dueDateProvided: $dueDateProvided,
			dueDate: $dueDateValue,
			assigneeProvided: $assigneeProvided,
			assignee: $assignee,
			checkedProvided: $checked !== null,
			checked: $checked ?? false,
		);

		return McpChecklistItemDto::fromEntity($updated);
	}

	/**
	 * Check or uncheck a checklist item.
	 *
	 * @param int $itemId Checklist item ID
	 * @param bool $checked True to mark done, false to mark not done
	 */
	#[McpTool(name: 'toggle_checklist_item', description: 'Check or uncheck a checklist item.')]
	public function toggleChecklistItem(int $itemId, bool $checked): McpChecklistItemDto
	{
		$user = $this->userContext->getUser();
		$item = $this->requireItem($itemId);

		$updated = $this->checklistProvider->setChecked($item, $user, $checked);

		return McpChecklistItemDto::fromEntity($updated);
	}

	/**
	 * Delete a checklist item.
	 *
	 * @param int $itemId Checklist item ID
	 */
	#[McpTool(name: 'delete_checklist_item', description: 'Delete a checklist item.')]
	public function deleteChecklistItem(int $itemId): string
	{
		$item = $this->requireItem($itemId);
		$this->checklistProvider->deleteItem($item);

		return 'Checklist item deleted.';
	}

	private function requireTask(int $taskId): Task
	{
		$task = $this->taskProvider->getTask($taskId);
		if ($task === null || !$this->workspaceProvider->isMember($this->userContext->getUser(), $task->project->workspace)) {
			throw new RuntimeException(sprintf('Task %d not found.', $taskId));
		}
		return $task;
	}

	private function requireItem(int $itemId): TaskChecklistItem
	{
		$item = $this->checklistProvider->getItem($itemId);
		if ($item === null || !$this->workspaceProvider->isMember($this->userContext->getUser(), $item->task->project->workspace)) {
			throw new RuntimeException(sprintf('Checklist item %d not found.', $itemId));
		}
		return $item;
	}

	private function requireMember(Task $task, int $userId): User
	{
		$member = $this->userRepository->findUserById($userId);
		if ($member === null || !$this->workspaceProvider->isMember($member, $task->project->workspace)) {
			throw new RuntimeException(sprintf('Assignee user %d must be a member of the task\'s workspace.', $userId));
		}
		return $member;
	}
}
