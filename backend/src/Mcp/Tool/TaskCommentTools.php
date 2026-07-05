<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use RuntimeException;
use Ukolio\Mcp\Dto\McpTaskCommentDto;
use Ukolio\Mcp\Dto\McpTaskCommentListDto;
use Ukolio\Mcp\McpUserContextInterface;
use Ukolio\Model\Entity\Task;
use Ukolio\Model\Entity\TaskComment;
use Ukolio\Service\Auth\PermissionCheckerInterface;
use Ukolio\Service\Provider\TaskCommentProviderInterface;
use Ukolio\Service\Provider\TaskProviderInterface;
use Ukolio\Service\Provider\WorkspaceProviderInterface;

final readonly class TaskCommentTools
{
	public function __construct(
		private McpUserContextInterface $userContext,
		private TaskProviderInterface $taskProvider,
		private TaskCommentProviderInterface $taskCommentProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private PermissionCheckerInterface $permissionChecker,
	) {
	}

	/**
	 * List comments on a task in chronological order.
	 *
	 * @param int $taskId Task ID
	 */
	#[McpTool(name: 'list_task_comments', description: 'List comments on a task in chronological order.')]
	public function listTaskComments(int $taskId): McpTaskCommentListDto
	{
		$task = $this->requireTask($taskId);
		$comments = array_map(
			static fn (TaskComment $c): McpTaskCommentDto => McpTaskCommentDto::fromEntity($c),
			$this->taskCommentProvider->findByTask($task),
		);
		return new McpTaskCommentListDto($comments);
	}

	/**
	 * Add a comment to a task. The comment is tagged as agent-authored
	 * and the MCP client name appears next to it in the UI. Pass parentCommentId
	 * to post it as a reply in that comment's thread. Mention a member with the
	 * token @[Display Name](user:ID).
	 *
	 * @param int $taskId Task ID
	 * @param string $body Markdown-formatted comment body (non-empty, max 10000 characters)
	 * @param int|null $parentCommentId Optional id of the comment this one replies to (must be on the same task)
	 */
	#[McpTool(name: 'add_task_comment', description: 'Add a comment to a task (agent-tagged); optionally as a threaded reply.')]
	public function addTaskComment(int $taskId, string $body, ?int $parentCommentId = null): McpTaskCommentDto
	{
		$user = $this->userContext->getUser();
		$task = $this->requireTask($taskId);

		$parent = null;
		if ($parentCommentId !== null) {
			$parent = $this->taskCommentProvider->getComment($parentCommentId);
			if ($parent === null || $parent->task->id !== $task->id) {
				throw new RuntimeException(sprintf('Parent comment %d not found on task %d.', $parentCommentId, $taskId));
			}
		}

		$comment = $this->taskCommentProvider->createComment($user, $task, $body, $parent);
		return McpTaskCommentDto::fromEntity($comment);
	}

	/**
	 * Edit the body of an existing comment. Only the comment's author may edit it.
	 *
	 * @param int $commentId Comment ID
	 * @param string $body New markdown-formatted comment body (non-empty, max 10000 characters)
	 */
	#[McpTool(name: 'update_task_comment', description: 'Edit the body of a comment (author only).')]
	public function updateTaskComment(int $commentId, string $body): McpTaskCommentDto
	{
		$user = $this->userContext->getUser();
		$comment = $this->taskCommentProvider->getComment($commentId);
		$workspace = $comment?->task->project->workspace;
		if ($comment === null || $workspace === null || !$this->workspaceProvider->isMember($user, $workspace)) {
			throw new RuntimeException(sprintf('Comment %d not found.', $commentId));
		}

		if (!$this->permissionChecker->canEditTaskComment($user, $workspace, $comment)) {
			throw new RuntimeException('You do not have permission to edit this comment.');
		}

		$updated = $this->taskCommentProvider->updateComment($user, $comment, $body);
		return McpTaskCommentDto::fromEntity($updated);
	}

	private function requireTask(int $taskId): Task
	{
		$task = $this->taskProvider->getTask($taskId);
		if ($task === null || !$this->workspaceProvider->isMember($this->userContext->getUser(), $task->project->workspace)) {
			throw new RuntimeException(sprintf('Task %d not found.', $taskId));
		}
		return $task;
	}
}
