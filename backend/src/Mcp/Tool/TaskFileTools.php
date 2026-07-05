<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use RuntimeException;
use Ukolio\Mcp\Dto\McpTaskFileContentDto;
use Ukolio\Mcp\Dto\McpTaskFileDto;
use Ukolio\Mcp\Dto\McpTaskFileListDto;
use Ukolio\Mcp\McpUserContextInterface;
use Ukolio\Model\Entity\Task;
use Ukolio\Model\Entity\TaskFile;
use Ukolio\Service\Provider\TaskFileProviderInterface;
use Ukolio\Service\Provider\TaskProviderInterface;
use Ukolio\Service\Provider\WorkspaceProviderInterface;

final readonly class TaskFileTools
{
	public function __construct(
		private McpUserContextInterface $userContext,
		private TaskProviderInterface $taskProvider,
		private TaskFileProviderInterface $taskFileProvider,
		private WorkspaceProviderInterface $workspaceProvider,
	) {
	}

	/**
	 * List files attached to a task.
	 *
	 * @param int $taskId Task ID
	 */
	#[McpTool(name: 'list_task_files', description: 'List files attached to a task.')]
	public function listTaskFiles(int $taskId): McpTaskFileListDto
	{
		$task = $this->requireTask($taskId);
		$files = array_map(
			static fn (TaskFile $file): McpTaskFileDto => McpTaskFileDto::fromEntity($file),
			$this->taskFileProvider->findByTask($task),
		);
		return new McpTaskFileListDto($files);
	}

	/**
	 * Attach a file to a task. The body must be base64-encoded.
	 * Decoded size must not exceed the server's max file size.
	 *
	 * @param int $taskId Task ID
	 * @param string $filename Original filename (e.g. "design.png")
	 * @param string $mimeType MIME type (e.g. "image/png"). Use "application/octet-stream" when unknown.
	 * @param string $contentBase64 Base64-encoded file contents
	 */
	#[McpTool(name: 'attach_file', description: 'Attach a base64-encoded file to a task.')]
	public function attachFile(int $taskId, string $filename, string $mimeType, string $contentBase64,): McpTaskFileDto
	{
		$user = $this->userContext->getUser();
		$task = $this->requireTask($taskId);

		$body = base64_decode($contentBase64, true);
		if ($body === false) {
			throw new RuntimeException('contentBase64 is not valid base64.');
		}

		$file = $this->taskFileProvider->uploadFile($user, $task, $filename, $mimeType, $body);
		return McpTaskFileDto::fromEntity($file);
	}

	/**
	 * Fetch a task file. Returns metadata plus base64-encoded contents.
	 * Use list_task_files first to discover the fileId.
	 *
	 * @param int $taskId Task ID
	 * @param int $fileId File ID
	 */
	#[McpTool(name: 'get_task_file', description: 'Fetch a task file as base64.')]
	public function getTaskFile(int $taskId, int $fileId): McpTaskFileContentDto
	{
		$task = $this->requireTask($taskId);
		$file = $this->requireFile($task, $fileId);

		$bytes = $this->taskFileProvider->readContent($file);

		return new McpTaskFileContentDto(
			id: $file->id,
			taskId: $file->task->id,
			filename: $file->filename,
			mimeType: $file->mimeType,
			size: $file->size,
			contentBase64: base64_encode($bytes),
		);
	}

	/**
	 * Delete a file from a task.
	 *
	 * @param int $taskId Task ID
	 * @param int $fileId File ID
	 */
	#[McpTool(name: 'delete_task_file', description: 'Delete a file from a task (irreversible).')]
	public function deleteTaskFile(int $taskId, int $fileId): string
	{
		$user = $this->userContext->getUser();
		$task = $this->requireTask($taskId);
		$file = $this->requireFile($task, $fileId);

		$this->taskFileProvider->deleteFile($user, $file);
		return 'File deleted.';
	}

	private function requireTask(int $taskId): Task
	{
		$task = $this->taskProvider->getTask($taskId);
		if ($task === null || !$this->workspaceProvider->isMember($this->userContext->getUser(), $task->project->workspace)) {
			throw new RuntimeException(sprintf('Task %d not found.', $taskId));
		}
		return $task;
	}

	private function requireFile(Task $task, int $fileId): TaskFile
	{
		$file = $this->taskFileProvider->getFile($fileId);
		if ($file === null || $file->task->id !== $task->id) {
			throw new RuntimeException(sprintf('File %d not found on task %d.', $fileId, $task->id));
		}
		return $file;
	}
}
